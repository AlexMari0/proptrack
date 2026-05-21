<?php

use App\Jobs\HandleMidtransWebhookJob;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);
});

// ─── Helpers ───────────────────────────────────────────────────────────────────

function paymentOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function paymentTenantUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function paymentMakeTenant(): Tenant
{
    return Tenant::create([
        'name'                    => 'Budi Santoso',
        'email'                   => fake()->unique()->safeEmail(),
        'phone'                   => '081234567890',
        'id_card_number'          => fake()->numerify('################'),
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);
}

function paymentMakeProperty(): Property
{
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    return Property::create([
        'name'          => 'Kos Test ' . fake()->unique()->numberBetween(1, 9999),
        'address'       => 'Jl. Test No. 1',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 1500000,
        'owner_id'      => $owner->id,
    ]);
}

function paymentMakeContract(Tenant $tenant, Property $property): Contract
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

function paymentMakeInvoice(Contract $contract, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'contract_id'    => $contract->id,
        'tenant_id'      => $contract->tenant_id,
        'property_id'    => $contract->property_id,
        'invoice_number' => 'INV-' . now()->year . '-' . fake()->unique()->numerify('####'),
        'status'         => 'unpaid',
        'amount'         => 1500000,
        'billing_month'  => now()->format('Y-m'),
        'due_date'       => now()->addDays(5),
    ], $overrides));
}

// ─── create-transaction ────────────────────────────────────────────────────────

test('unauthenticated cannot create transaction', function () {
    $this->postJson('/api/v1/payments/create-transaction', [
        'invoice_id' => fake()->uuid(),
        'gateway'    => 'midtrans',
    ])->assertStatus(401);
});

test('create-transaction validates invoice_id exists', function () {
    $this->actingAs(paymentOwner())
        ->postJson('/api/v1/payments/create-transaction', [
            'invoice_id' => fake()->uuid(),
            'gateway'    => 'midtrans',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_id']);
});

test('create-transaction validates gateway is midtrans', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $this->actingAs(paymentOwner())
        ->postJson('/api/v1/payments/create-transaction', [
            'invoice_id' => $invoice->id,
            'gateway'    => 'unknown',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['gateway']);
});

test('create-transaction returns token from midtrans', function () {
    Http::fake([
        '*midtrans*' => Http::response([
            'token'        => 'snap-token-abc123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-abc123',
        ], 200),
    ]);

    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $response = $this->actingAs(paymentOwner())
        ->postJson('/api/v1/payments/create-transaction', [
            'invoice_id' => $invoice->id,
            'gateway'    => 'midtrans',
        ])->assertStatus(200)
        ->assertJsonStructure(['data' => ['transaction_token', 'redirect_url', 'invoice_id']])
        ->assertJsonPath('data.transaction_token', 'snap-token-abc123')
        ->assertJsonPath('data.invoice_id', $invoice->id);

    // Token must be persisted on the invoice
    expect($invoice->fresh()->payment_token)->toBe('snap-token-abc123');
    expect($invoice->fresh()->payment_gateway)->toBe('midtrans');
});

test('cannot create transaction for already paid invoice', function () {
    Http::fake();

    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract, ['status' => 'paid', 'paid_at' => now()]);

    $this->actingAs(paymentOwner())
        ->postJson('/api/v1/payments/create-transaction', [
            'invoice_id' => $invoice->id,
            'gateway'    => 'midtrans',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_id']);
});

test('create-transaction returns 422 when midtrans API fails', function () {
    Http::fake([
        '*midtrans*' => Http::response(['error_messages' => ['Server key required']], 401),
    ]);

    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $this->actingAs(paymentOwner())
        ->postJson('/api/v1/payments/create-transaction', [
            'invoice_id' => $invoice->id,
            'gateway'    => 'midtrans',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['gateway']);
});

// ─── status ────────────────────────────────────────────────────────────────────

test('can poll payment status for unpaid invoice', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $this->actingAs(paymentOwner())
        ->getJson("/api/v1/payments/{$invoice->id}/status")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'unpaid')
        ->assertJsonPath('data.invoice_id', $invoice->id)
        ->assertJsonStructure(['data' => ['invoice_id', 'status', 'paid_at', 'payment_gateway']]);
});

test('status shows paid for paid invoice', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract, [
        'status'          => 'paid',
        'paid_at'         => now(),
        'payment_gateway' => 'midtrans',
    ]);

    $this->actingAs(paymentOwner())
        ->getJson("/api/v1/payments/{$invoice->id}/status")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payment_gateway', 'midtrans');
});

// ─── Webhook ──────────────────────────────────────────────────────────────────

test('midtrans webhook endpoint is public (no auth required)', function () {
    // Valid signature not checked here — just verifying no 401
    $this->postJson('/api/v1/payments/webhook/midtrans', [])->assertStatus(403);
    // 403 means it reached the controller (invalid sig) not a 401
});

test('midtrans webhook rejects invalid signature', function () {
    $this->postJson('/api/v1/payments/webhook/midtrans', [
        'order_id'         => 'INV-2025-0001',
        'status_code'      => '200',
        'gross_amount'     => '1500000.00',
        'signature_key'    => 'invalid',
        'transaction_status' => 'settlement',
    ])->assertStatus(403)
      ->assertJsonPath('message', 'Invalid signature');
});

test('midtrans webhook with valid signature dispatches job', function () {
    Bus::fake();

    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $serverKey   = config('services.midtrans.server_key', '');
    $orderId     = $invoice->invoice_number;
    $statusCode  = '200';
    $grossAmount = '1500000.00';
    $signature   = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $this->postJson('/api/v1/payments/webhook/midtrans', [
        'order_id'           => $orderId,
        'status_code'        => $statusCode,
        'gross_amount'       => $grossAmount,
        'signature_key'      => $signature,
        'transaction_status' => 'settlement',
        'fraud_status'       => 'accept',
    ])->assertStatus(200)
      ->assertJsonPath('message', 'Webhook received');

    Bus::assertDispatched(HandleMidtransWebhookJob::class);
});

// ─── HandleMidtransWebhookJob ─────────────────────────────────────────────────

test('webhook job marks invoice as paid on settlement', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $job = new HandleMidtransWebhookJob([
        'order_id'           => $invoice->invoice_number,
        'transaction_status' => 'settlement',
        'fraud_status'       => 'accept',
        'status_code'        => '200',
        'gross_amount'       => '1500000.00',
    ]);
    $job->handle(app(PaymentService::class));

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->paid_at)->not->toBeNull();
    expect($invoice->fresh()->payment_gateway)->toBe('midtrans');
});

test('webhook job marks invoice as paid on capture+accept', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $job = new HandleMidtransWebhookJob([
        'order_id'           => $invoice->invoice_number,
        'transaction_status' => 'capture',
        'fraud_status'       => 'accept',
        'status_code'        => '200',
        'gross_amount'       => '1500000.00',
    ]);
    $job->handle(app(PaymentService::class));

    expect($invoice->fresh()->status)->toBe('paid');
});

test('webhook job ignores pending status', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract);

    $job = new HandleMidtransWebhookJob([
        'order_id'           => $invoice->invoice_number,
        'transaction_status' => 'pending',
        'fraud_status'       => '',
        'status_code'        => '201',
        'gross_amount'       => '1500000.00',
    ]);
    $job->handle(app(PaymentService::class));

    expect($invoice->fresh()->status)->toBe('unpaid');
});

test('webhook job is idempotent — does not double-process paid invoice', function () {
    $tenant   = paymentMakeTenant();
    $property = paymentMakeProperty();
    $contract = paymentMakeContract($tenant, $property);
    $invoice  = paymentMakeInvoice($contract, [
        'status'          => 'paid',
        'paid_at'         => now()->subMinute(),
        'payment_gateway' => 'midtrans',
    ]);

    $originalPaidAt = $invoice->paid_at;

    $job = new HandleMidtransWebhookJob([
        'order_id'           => $invoice->invoice_number,
        'transaction_status' => 'settlement',
        'fraud_status'       => 'accept',
        'status_code'        => '200',
        'gross_amount'       => '1500000.00',
    ]);
    $job->handle(app(PaymentService::class));

    // paid_at should not have changed
    expect($invoice->fresh()->paid_at->toDateTimeString())
        ->toBe($originalPaidAt->toDateTimeString());
});

// ─── Xendit webhook placeholder ───────────────────────────────────────────────

test('xendit webhook returns 200', function () {
    $this->postJson('/api/v1/payments/webhook/xendit', [])->assertStatus(200);
});
