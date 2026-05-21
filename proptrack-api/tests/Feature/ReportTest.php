<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'owner']);
    Role::firstOrCreate(['name' => 'agent']);
    Role::firstOrCreate(['name' => 'tenant']);
});

// ─── Unique Helpers ──────────────────────────────────────────────────────────

function reportOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function reportAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function reportAgent(): User
{
    $user = User::factory()->create();
    $user->assignRole('agent');
    return $user;
}

function reportTenantUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function reportMakeTenant(): Tenant
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

function reportMakeProperty(User $owner): Property
{
    return Property::create([
        'name'          => 'Kos Harmoni ' . fake()->unique()->numberBetween(1, 9999),
        'address'       => 'Jl. Harmoni No. 1',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 1500000,
        'owner_id'      => $owner->id,
    ]);
}

function reportMakeContract(Tenant $tenant, Property $property): Contract
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

function reportMakeInvoice(Contract $contract, array $overrides = []): Invoice
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

// ─── Tests ───────────────────────────────────────────────────────────────────

test('unauthenticated user cannot access financial reports', function () {
    $this->getJson('/api/v1/reports/financial?year=2026')
        ->assertStatus(401);
});

test('tenant and agent role cannot access financial reports', function () {
    $this->actingAs(reportTenantUser())
        ->getJson('/api/v1/reports/financial?year=2026')
        ->assertStatus(403);

    $this->actingAs(reportAgent())
        ->getJson('/api/v1/reports/financial?year=2026')
        ->assertStatus(403);
});

test('admin can view overall financial reports across all properties', function () {
    $admin = reportAdmin();
    $owner1 = reportOwner();
    $owner2 = reportOwner();

    $prop1 = reportMakeProperty($owner1);
    $prop2 = reportMakeProperty($owner2);

    $t1 = reportMakeTenant();
    $t2 = reportMakeTenant();

    $c1 = reportMakeContract($t1, $prop1);
    $c2 = reportMakeContract($t2, $prop2);

    reportMakeInvoice($c1, ['amount' => 1000000, 'status' => 'paid', 'billing_month' => '2026-05']);
    reportMakeInvoice($c2, ['amount' => 2000000, 'status' => 'unpaid', 'billing_month' => '2026-05']);

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/financial?year=2026&month=5')
        ->assertStatus(200)
        ->assertJsonPath('data.period', '2026-05')
        ->assertJsonPath('data.total_invoiced', 3000000)
        ->assertJsonPath('data.total_collected', 1000000)
        ->assertJsonPath('data.total_outstanding', 2000000)
        ->assertJsonPath('data.collection_rate', 33.3)
        ->assertJsonCount(2, 'data.by_property');
});

test('owner can only view financial aggregates of their own properties', function () {
    $owner = reportOwner();
    $otherOwner = reportOwner();

    $ownProp = reportMakeProperty($owner);
    $otherProp = reportMakeProperty($otherOwner);

    $t1 = reportMakeTenant();
    $t2 = reportMakeTenant();

    $c1 = reportMakeContract($t1, $ownProp);
    $c2 = reportMakeContract($t2, $otherProp);

    reportMakeInvoice($c1, ['amount' => 1500000, 'status' => 'paid', 'billing_month' => '2026-05']);
    reportMakeInvoice($c2, ['amount' => 5000000, 'status' => 'unpaid', 'billing_month' => '2026-05']);

    $this->actingAs($owner)
        ->getJson('/api/v1/reports/financial?year=2026&month=5')
        ->assertStatus(200)
        ->assertJsonPath('data.total_invoiced', 1500000)
        ->assertJsonPath('data.total_collected', 1500000)
        ->assertJsonPath('data.total_outstanding', 0)
        ->assertJsonPath('data.collection_rate', 100)
        ->assertJsonCount(1, 'data.by_property')
        ->assertJsonPath('data.by_property.0.property_name', $ownProp->name);
});

test('year is required and must be validated', function () {
    $admin = reportAdmin();

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/financial')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['year']);
});

test('owner can access single property financial details if owned', function () {
    $owner = reportOwner();
    $prop = reportMakeProperty($owner);
    $t = reportMakeTenant();
    $c = reportMakeContract($t, $prop);

    reportMakeInvoice($c, ['amount' => 1200000, 'status' => 'paid', 'billing_month' => '2026-05']);

    $this->actingAs($owner)
        ->getJson("/api/v1/reports/financial/{$prop->id}?year=2026")
        ->assertStatus(200)
        ->assertJsonPath('data.total_invoiced', 1200000)
        ->assertJsonPath('data.total_collected', 1200000);
});

test('owner cannot access single property financial details of another owner', function () {
    $owner = reportOwner();
    $otherOwner = reportOwner();
    $otherProp = reportMakeProperty($otherOwner);

    $this->actingAs($owner)
        ->getJson("/api/v1/reports/financial/{$otherProp->id}?year=2026")
        ->assertStatus(403);
});

test('export returns a binary PDF file response', function () {
    $admin = reportAdmin();
    $owner = reportOwner();
    $prop = reportMakeProperty($owner);
    $t = reportMakeTenant();
    $c = reportMakeContract($t, $prop);

    reportMakeInvoice($c, ['amount' => 1000000, 'status' => 'paid', 'billing_month' => '2026-05']);

    // Mock PDF generation so we don't need a real headless Chrome driver in testing
    \Spatie\LaravelPdf\Facades\Pdf::fake();

    // The export endpoint generates a report array and hashes it. Let's pre-generate the expected hash
    // and write a dummy file so response()->download() succeeds without throwing a FileNotFoundException.
    $summary = [
        'period'            => '2026-05',
        'total_invoiced'    => 1000000,
        'total_collected'   => 1000000,
        'total_outstanding' => 0,
        'collection_rate'   => 100.0,
        'by_property'       => [
            [
                'property_id'   => $prop->id,
                'property_name' => $prop->name,
                'invoiced'      => 1000000,
                'collected'     => 1000000,
                'outstanding'   => 0,
            ]
        ],
    ];
    $hash = md5(json_encode($summary));
    $path = "reports/report-{$hash}.pdf";
    \Illuminate\Support\Facades\Storage::disk('local')->put($path, '%PDF-1.4 dummy pdf content');

    $response = $this->actingAs($admin)
        ->get('/api/v1/reports/financial/export?year=2026&month=5');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('financial-report-2026-05.pdf');
});
