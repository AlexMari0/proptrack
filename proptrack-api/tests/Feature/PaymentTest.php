<?php

use App\Jobs\SettlePaymentJob;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
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

/** The owner of every property made by paymentMakeProperty() (owners only see their own). */
function paymentOwner(): User
{
    if ($existing = User::role('owner')->orderBy('id')->first()) {
        return $existing;
    }

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

/** A Tenant with a linked tenant account (the payer). */
function paymentMakeTenant(): Tenant
{
    $user = paymentTenantUser();

    return Tenant::create([
        'user_id'                 => $user->id,
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
    $owner = paymentOwner();

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

/** POST a create-transaction request as the invoice's own tenant. */
function paymentCheckout($test, Invoice $invoice, string $gateway = 'midtrans')
{
    return $test->actingAs($invoice->tenant->user)
        ->postJson('/api/v1/payments/create-transaction', ['invoice_id' => $invoice->id, 'gateway' => $gateway]);
}

/** A Midtrans notification signed with the configured server key. */
function paymentSignedNotification(Invoice $invoice, array $overrides = []): array
{
    $payload = array_merge([
        'order_id'           => $invoice->invoice_number,
        'status_code'        => '200',
        'gross_amount'       => number_format($invoice->amount, 2, '.', ''),
        'transaction_status' => 'settlement',
        'fraud_status'       => 'accept',
    ], $overrides);

    $payload['signature_key'] = hash('sha512',
        $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . config('services.midtrans.server_key')
    );

    return $payload;
}

function paymentInvoice(array $overrides = []): Invoice
{
    return paymentMakeInvoice(paymentMakeContract(paymentMakeTenant(), paymentMakeProperty()), $overrides);
}

// ─── Checkout ─────────────────────────────────────────────────────────────────

test('unauthenticated cannot create transaction', function () {
    $this->postJson('/api/v1/payments/create-transaction', [
        'invoice_id' => fake()->uuid(),
        'gateway'    => 'midtrans',
    ])->assertStatus(401);
});

test('create-transaction validates invoice_id and gateway', function () {
    $this->actingAs(paymentTenantUser())
        ->postJson('/api/v1/payments/create-transaction', ['invoice_id' => fake()->uuid(), 'gateway' => 'midtrans'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_id']);

    paymentCheckout($this, paymentInvoice(), 'unknown')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gateway']);
});

test('the tenant opens a Midtrans checkout for their invoice', function () {
    Http::fake([
        '*midtrans*' => Http::response([
            'token'        => 'snap-token-abc123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-abc123',
        ], 200),
    ]);
    $invoice = paymentInvoice();

    paymentCheckout($this, $invoice)
        ->assertStatus(200)
        ->assertJsonPath('data.transaction_token', 'snap-token-abc123')
        ->assertJsonPath('data.simulated', false)
        ->assertJsonPath('data.invoice_id', $invoice->id);

    expect($invoice->fresh()->payment_token)->toBe('snap-token-abc123');
    Http::assertSent(fn ($request) => $request['transaction_details']['order_id'] === $invoice->invoice_number
        && $request['transaction_details']['gross_amount'] === 1500000);
});

test('only the invoice\'s own tenant can open a checkout', function () {
    Http::fake();
    $invoice = paymentInvoice();

    $this->actingAs(paymentOwner())
        ->postJson('/api/v1/payments/create-transaction', ['invoice_id' => $invoice->id, 'gateway' => 'midtrans'])
        ->assertStatus(403);

    $this->actingAs(paymentMakeTenant()->user)
        ->postJson('/api/v1/payments/create-transaction', ['invoice_id' => $invoice->id, 'gateway' => 'midtrans'])
        ->assertStatus(403);

    Http::assertNothingSent();
});

test('a paid invoice cannot be checked out', function () {
    Http::fake();

    paymentCheckout($this, paymentInvoice(['status' => 'paid', 'paid_at' => now()]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_id']);

    Http::assertNothingSent();
});

test('a Midtrans failure is a 422 gateway error, never a mock token', function () {
    Http::fake(['*midtrans*' => Http::response(['error_messages' => ['Server key required']], 401)]);
    $this->app['env'] = 'local';

    paymentCheckout($this, paymentInvoice())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gateway']);
});

// ─── Status polling ───────────────────────────────────────────────────────────

test('status polling returns the effective status', function () {
    $unpaid = paymentInvoice();
    $paid = paymentInvoice(['status' => 'paid', 'paid_at' => now(), 'payment_gateway' => 'midtrans']);

    $this->actingAs(paymentOwner())
        ->getJson("/api/v1/payments/{$unpaid->id}/status")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'unpaid')
        ->assertJsonPath('data.invoice_id', $unpaid->id)
        ->assertJsonStructure(['data' => ['invoice_id', 'status', 'paid_at', 'payment_gateway']]);

    $this->actingAs(paymentOwner())
        ->getJson("/api/v1/payments/{$paid->id}/status")
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payment_gateway', 'midtrans');
});

// ─── Midtrans notifications ───────────────────────────────────────────────────
// QUEUE_CONNECTION=sync in tests, so SettlePaymentJob runs inline.

test('the webhook is public and rejects a missing or bad signature', function () {
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', [])->assertStatus(403);

    $this->postJson('/api/v1/payments/webhook/midtrans', ['signature_key' => 'invalid'] + paymentSignedNotification($invoice))
        ->assertStatus(403)
        ->assertJsonPath('message', 'Invalid signature');

    expect($invoice->fresh()->status)->toBe('unpaid');
});

test('a signed settlement marks the invoice paid via midtrans', function (array $status) {
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentSignedNotification($invoice, $status))
        ->assertStatus(200)
        ->assertJsonPath('message', 'Webhook received');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->payment_gateway)->toBe('midtrans');
})->with([
    'settlement'     => [['transaction_status' => 'settlement']],
    'capture+accept' => [['transaction_status' => 'capture', 'fraud_status' => 'accept']],
]);

test('non-final or failed notifications leave the invoice unpaid', function (array $status) {
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentSignedNotification($invoice, $status))
        ->assertStatus(200);

    expect($invoice->fresh()->status)->toBe('unpaid');
})->with([
    'pending'           => [['transaction_status' => 'pending']],
    'capture+challenge' => [['transaction_status' => 'capture', 'fraud_status' => 'challenge']],
    'deny'              => [['transaction_status' => 'deny']],
    'expire'            => [['transaction_status' => 'expire']],
]);

test('a settlement for the wrong amount is ignored', function () {
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentSignedNotification($invoice, ['gross_amount' => '1000.00']))
        ->assertStatus(200);

    expect($invoice->fresh()->status)->toBe('unpaid');
});

test('a repeated settlement is idempotent', function () {
    $invoice = paymentInvoice(['status' => 'paid', 'paid_at' => now()->subDay(), 'payment_gateway' => 'midtrans']);
    $paidAt = $invoice->paid_at->toIso8601String();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentSignedNotification($invoice))
        ->assertStatus(200);

    expect($invoice->fresh()->paid_at->toIso8601String())->toBe($paidAt);
});

test('settlement is queued, not run in the request', function () {
    Bus::fake();
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentSignedNotification($invoice))
        ->assertStatus(200);

    Bus::assertDispatched(SettlePaymentJob::class, fn ($job) => $job->notification['invoiceNumber'] === $invoice->invoice_number);
    expect($invoice->fresh()->status)->toBe('unpaid');
});

// ─── Payment simulation (local dev) ──────────────────────────────────────────

function paymentMockWebhook(Invoice $invoice): array
{
    return [
        'order_id'           => $invoice->invoice_number,
        'status_code'        => '200',
        'gross_amount'       => (string) $invoice->amount,
        'transaction_status' => 'settlement',
        'mock'               => true,
    ];
}

test('with simulation on, checkout is simulated and the simulator settles the invoice', function () {
    Http::fake();
    config(['services.midtrans.simulate' => true]);
    $invoice = paymentInvoice();

    paymentCheckout($this, $invoice)
        ->assertStatus(200)
        ->assertJsonPath('data.simulated', true);

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentMockWebhook($invoice))->assertStatus(200);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->payment_gateway)->toBe('simulated');
    Http::assertNothingSent();
});

test('unsigned mock webhook is rejected when simulation is off, even in local env', function () {
    $this->app['env'] = 'local';
    config(['services.midtrans.simulate' => false]);
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentMockWebhook($invoice))->assertStatus(403);

    expect($invoice->fresh()->status)->toBe('unpaid');
});

test('simulation is ignored in production even if switched on', function (string $how) {
    Http::fake(['*midtrans*' => Http::response(['token' => 't', 'redirect_url' => 'https://x'], 200)]);
    config(['services.midtrans.simulate' => true]);
    if ($how === 'app') {
        $this->app['env'] = 'production';
    } else {
        config(['services.midtrans.is_production' => true]);
    }
    $invoice = paymentInvoice();

    $this->postJson('/api/v1/payments/webhook/midtrans', paymentMockWebhook($invoice))->assertStatus(403);
    paymentCheckout($this, $invoice)->assertJsonPath('data.simulated', false);

    expect($invoice->fresh()->status)->toBe('unpaid');
})->with(['app' => 'app', 'midtrans' => 'midtrans']);

// ─── Xendit ───────────────────────────────────────────────────────────────────

test('xendit webhook returns 200', function () {
    $this->postJson('/api/v1/payments/webhook/xendit', [])->assertStatus(200);
});
