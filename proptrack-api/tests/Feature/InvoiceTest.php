<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Jobs\GenerateInvoicePdfJob;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);
});

// ─── Helpers ───────────────────────────────────────────────────────────────────

function invoiceOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function invoiceAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function invoiceAgent(): User
{
    $user = User::factory()->create();
    $user->assignRole('agent');
    return $user;
}

function invoiceTenantUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function invoiceMakeTenant(): Tenant
{
    return Tenant::create([
        'name'                    => 'Ani Wijaya',
        'email'                   => fake()->unique()->safeEmail(),
        'phone'                   => '081234567890',
        'id_card_number'          => fake()->numerify('################'),
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);
}

function invoiceMakeProperty(): Property
{
    $propertyOwner = User::factory()->create();
    $propertyOwner->assignRole('owner');

    return Property::create([
        'name'          => 'Kos Harmoni ' . fake()->unique()->numberBetween(1, 9999),
        'address'       => 'Jl. Harmoni No. 1',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 1500000,
        'owner_id'      => $propertyOwner->id,
    ]);
}

function invoiceMakeContract(Tenant $tenant, Property $property): Contract
{
    return Contract::create([
        'tenant_id'      => $tenant->id,
        'property_id'    => $property->id,
        'start_date'     => now()->subMonth(),
        'end_date'       => now()->addYear(),
        'monthly_rent'   => 1500000,
        'deposit_amount' => 3000000,
        'billing_date'   => 5,
        'status'         => 'active',
    ]);
}

function invoiceMakeInvoice(Contract $contract, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'contract_id'    => $contract->id,
        'tenant_id'      => $contract->tenant_id,
        'property_id'    => $contract->property_id,
        'invoice_number' => 'INV-' . now()->year . '-' . fake()->unique()->numerify('####'),
        'status'         => 'unpaid',
        'amount'         => $contract->monthly_rent,
        'billing_month'  => now()->format('Y-m'),
        'due_date'       => now()->addDays(5),
    ], $overrides));
}

// ─── Auth ──────────────────────────────────────────────────────────────────────

test('unauthenticated user cannot access invoices', function () {
    $this->getJson('/api/v1/invoices')->assertStatus(401);
});

test('tenant role can list own invoices and gets filtered results', function () {
    $tenantUser = invoiceTenantUser();
    $tenant = Tenant::create([
        'user_id'                 => $tenantUser->id,
        'name'                    => 'Ani Wijaya',
        'email'                   => $tenantUser->email,
        'phone'                   => '081234567890',
        'id_card_number'          => '1234567890123456',
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);
    
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);
    $invoice1 = invoiceMakeInvoice($contract);
    
    // Create another invoice for a different tenant
    $otherTenant = invoiceMakeTenant();
    $otherContract = invoiceMakeContract($otherTenant, $property);
    $invoice2 = invoiceMakeInvoice($otherContract);

    $this->actingAs($tenantUser)
        ->getJson('/api/v1/invoices')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $invoice1->id);
});

test('agent cannot view invoices', function () {
    $this->actingAs(invoiceAgent())->getJson('/api/v1/invoices')->assertStatus(403);
});

test('agent cannot view single invoice', function () {
    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);
    $invoice = invoiceMakeInvoice($contract);

    $this->actingAs(invoiceAgent())
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertStatus(403);
});

// ─── Index / Filters ───────────────────────────────────────────────────────────

test('owner can list invoices with meta', function () {
    $owner = invoiceOwner();
    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);
    invoiceMakeInvoice($contract);

    $this->actingAs($owner)
        ->getJson('/api/v1/invoices')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'last_page', 'per_page'], 'message'])
        ->assertJsonPath('meta.total', 1);
});

test('invoices can be filtered by status', function () {
    $owner = invoiceOwner();
    $tenant = invoiceMakeTenant();
    $property1 = invoiceMakeProperty();
    $property2 = invoiceMakeProperty();
    $contract1 = invoiceMakeContract($tenant, $property1);
    $contract2 = invoiceMakeContract($tenant, $property2);

    invoiceMakeInvoice($contract1, ['status' => 'unpaid']);
    invoiceMakeInvoice($contract2, ['status' => 'paid', 'paid_at' => now(), 'billing_month' => now()->subMonth()->format('Y-m')]);

    $this->actingAs($owner)
        ->getJson('/api/v1/invoices?status=unpaid')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

test('invoices can be filtered by property_id', function () {
    $owner = invoiceOwner();
    $tenant = invoiceMakeTenant();
    $property1 = invoiceMakeProperty();
    $property2 = invoiceMakeProperty();
    $contract1 = invoiceMakeContract($tenant, $property1);
    $contract2 = invoiceMakeContract($tenant, $property2);

    invoiceMakeInvoice($contract1);
    invoiceMakeInvoice($contract2);

    $this->actingAs($owner)
        ->getJson("/api/v1/invoices?property_id={$property1->id}")
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

test('invoices can be filtered by billing month', function () {
    $owner = invoiceOwner();
    $tenant = invoiceMakeTenant();
    $property1 = invoiceMakeProperty();
    $property2 = invoiceMakeProperty();
    $contract1 = invoiceMakeContract($tenant, $property1);
    $contract2 = invoiceMakeContract($tenant, $property2);

    invoiceMakeInvoice($contract1, ['billing_month' => '2025-01']);
    invoiceMakeInvoice($contract2, ['billing_month' => '2025-02']);

    $this->actingAs($owner)
        ->getJson('/api/v1/invoices?month=2025-01')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

// ─── Show ──────────────────────────────────────────────────────────────────────

test('owner can view a single invoice', function () {
    $owner = invoiceOwner();
    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);
    $invoice = invoiceMakeInvoice($contract);

    $this->actingAs($owner)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
        ->assertJsonPath('data.status', 'unpaid')
        ->assertJsonPath('data.amount', 1500000)
        ->assertJsonStructure(['data' => ['id', 'invoice_number', 'status', 'amount', 'billing_month', 'due_date', 'tenant', 'property', 'contract']]);
});

// ─── Send notification ─────────────────────────────────────────────────────────

test('owner can send invoice notification', function () {
    $owner = invoiceOwner();
    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);
    $invoice = invoiceMakeInvoice($contract);

    $this->actingAs($owner)
        ->postJson("/api/v1/invoices/{$invoice->id}/send")
        ->assertStatus(200)
        ->assertJsonPath('message', 'Invoice notification queued successfully');
});

test('agent cannot send invoice notification', function () {
    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);
    $invoice = invoiceMakeInvoice($contract);

    $this->actingAs(invoiceAgent())
        ->postJson("/api/v1/invoices/{$invoice->id}/send")
        ->assertStatus(403);
});

// ─── GenerateMonthlyInvoicesCommand ────────────────────────────────────────────

test('artisan command generates invoices for active contracts', function () {
    Bus::fake();

    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    invoiceMakeContract($tenant, $property); // billing_date=5

    $billingMonth = now()->format('Y-m');

    $this->artisan("invoices:generate --month={$billingMonth}")
        ->assertExitCode(0);

    $this->assertDatabaseHas('invoices', [
        'tenant_id'     => $tenant->id,
        'billing_month' => $billingMonth,
    ]);

    Bus::assertDispatched(GenerateInvoicePdfJob::class);
});

test('artisan command skips already-generated invoices', function () {
    Bus::fake();

    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);

    $billingMonth = now()->format('Y-m');

    // Create invoice for this month already
    invoiceMakeInvoice($contract, ['billing_month' => $billingMonth]);

    $this->artisan("invoices:generate --month={$billingMonth}")
        ->assertExitCode(0);

    // Should still be just 1 invoice
    expect(Invoice::count())->toBe(1);
    Bus::assertNotDispatched(GenerateInvoicePdfJob::class);
});

test('artisan command only generates for active contracts', function () {
    Bus::fake();

    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $terminatedContract = invoiceMakeContract($tenant, $property);
    $terminatedContract->update(['status' => 'terminated', 'terminated_at' => now()]);

    $this->artisan('invoices:generate --month=' . now()->format('Y-m'))
        ->assertExitCode(0);

    expect(Invoice::count())->toBe(0);
});

test('past-due unpaid invoices list as overdue without any job running', function () {
    $this->travelTo(Carbon::parse('2026-06-10 05:00:00', 'UTC')); // 12:00 in Jakarta

    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);

    invoiceMakeInvoice($contract, ['billing_month' => '2026-05', 'due_date' => '2026-06-09']);
    invoiceMakeInvoice($contract, ['billing_month' => '2026-06', 'due_date' => '2026-06-10']);

    $this->actingAs(invoiceOwner())
        ->getJson('/api/v1/invoices?status=overdue')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.billing_month', '2026-05')
        ->assertJsonPath('data.0.status', 'overdue');

    expect(Invoice::where('status', 'overdue')->count())->toBe(0);
});

test('artisan command rejects invalid month format', function () {
    $this->artisan('invoices:generate --month=invalid')
        ->assertExitCode(1);
});

// ─── Invoice numbering ────────────────────────────────────────────────────

test('invoice numbers follow INV-YYYY-NNNN format', function () {
    Bus::fake();

    $tenant = invoiceMakeTenant();
    $property = invoiceMakeProperty();
    $contract = invoiceMakeContract($tenant, $property);

    $invoice = Invoice::issueFor($contract, now()->format('Y-m'));

    expect($invoice->invoice_number)->toMatch('/^INV-\d{4}-\d{4}$/');
});
