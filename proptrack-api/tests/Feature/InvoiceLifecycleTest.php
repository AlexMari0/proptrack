<?php

use App\Events\InvoiceIssued;
use App\Events\InvoicePaid;
use App\Events\PaymentConfirmed;
use App\Exceptions\InvalidInvoiceTransition;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/*
 * The Invoice lifecycle module, tested through its interface:
 * Invoice::issueFor, markPaid, effective status, scopes, and the API shape.
 * Overdue is derived in the billing timezone (docs/adr/0001-overdue-is-derived.md).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'owner', 'agent', 'tenant'] as $role) {
        Role::create(['name' => $role]);
    }
    // 2026-06-10 05:00 UTC = 12:00 in Jakarta: both calendars agree on the date.
    $this->travelTo(Carbon::parse('2026-06-10 05:00:00', 'UTC'));
});

function lifecycleContract(array $overrides = []): Contract
{
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    $tenant = Tenant::create([
        'name'                    => 'Ani Wijaya',
        'email'                   => fake()->unique()->safeEmail(),
        'phone'                   => '081234567890',
        'id_card_number'          => fake()->numerify('################'),
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);

    $property = Property::create([
        'name'          => 'Kos Harmoni ' . fake()->unique()->numberBetween(1, 9999),
        'address'       => 'Jl. Harmoni No. 1',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 1500000,
        'owner_id'      => $owner->id,
    ]);

    return Contract::create(array_merge([
        'tenant_id'      => $tenant->id,
        'property_id'    => $property->id,
        'start_date'     => '2026-01-01',
        'end_date'       => '2027-01-01',
        'monthly_rent'   => 1500000,
        'deposit_amount' => 3000000,
        'billing_date'   => 5,
        'status'         => 'active',
    ], $overrides));
}

function lifecycleInvoice(array $overrides = []): Invoice
{
    $contract = lifecycleContract();

    return Invoice::create(array_merge([
        'contract_id'    => $contract->id,
        'tenant_id'      => $contract->tenant_id,
        'property_id'    => $contract->property_id,
        'invoice_number' => 'INV-2026-' . fake()->unique()->numerify('####'),
        'status'         => 'unpaid',
        'amount'         => 1500000,
        'billing_month'  => '2026-06',
        'due_date'       => '2026-06-15',
    ], $overrides));
}

function lifecycleAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

// ─── issueFor ─────────────────────────────────────────────────────────────────

test('issueFor creates an unpaid invoice due on the billing day and dispatches InvoiceIssued', function () {
    Event::fake([InvoiceIssued::class]);
    $contract = lifecycleContract(['billing_date' => 20]);

    $invoice = Invoice::issueFor($contract, '2026-07');

    expect($invoice->status)->toBe('unpaid')
        ->and($invoice->amount)->toBe(1500000)
        ->and($invoice->due_date->toDateString())->toBe('2026-07-20')
        ->and($invoice->invoice_number)->toMatch('/^INV-2026-\d{4}$/');
    Event::assertDispatchedTimes(InvoiceIssued::class, 1);
});

test('issueFor returns null and dispatches nothing when the month is already invoiced', function () {
    Event::fake([InvoiceIssued::class]);
    $contract = lifecycleContract();
    Invoice::issueFor($contract, '2026-07');

    expect(Invoice::issueFor($contract, '2026-07'))->toBeNull()
        ->and(Invoice::count())->toBe(1);
    Event::assertDispatchedTimes(InvoiceIssued::class, 1);
});

test('issueFor clamps the due date to the last day of short months', function () {
    Event::fake([InvoiceIssued::class]);
    $this->travelTo(Carbon::parse('2026-01-31 05:00:00', 'UTC')); // a 31st must not overflow February

    $invoice = Invoice::issueFor(lifecycleContract(['billing_date' => 31]), '2026-02');

    expect($invoice->due_date->toDateString())->toBe('2026-02-28');
});

test('issuing queues the PDF and notifies the tenant', function () {
    Bus::fake([GenerateInvoicePdfJob::class]);
    Notification::fake();
    $contract = lifecycleContract();

    Invoice::issueFor($contract, '2026-07');

    Bus::assertDispatched(GenerateInvoicePdfJob::class);
    Notification::assertSentTo($contract->tenant, InvoiceCreatedNotification::class);
});

// ─── markPaid ─────────────────────────────────────────────────────────────────

test('markPaid records settlement and dispatches InvoicePaid once', function () {
    Event::fake([InvoicePaid::class]);
    $invoice = lifecycleInvoice();

    expect($invoice->markPaid('midtrans'))->toBeTrue()
        ->and($invoice->isPaid())->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->payment_gateway)->toBe('midtrans')
        ->and($invoice->fresh()->paid_at->toIso8601String())->toBe(now()->toIso8601String());

    expect($invoice->fresh()->markPaid('midtrans'))->toBeFalse();
    Event::assertDispatchedTimes(InvoicePaid::class, 1);
});

test('an overdue invoice can still be paid', function () {
    $invoice = lifecycleInvoice(['due_date' => '2026-06-01']);
    expect($invoice->isOverdue())->toBeTrue();

    expect($invoice->markPaid('midtrans'))->toBeTrue()
        ->and($invoice->isOverdue())->toBeFalse()
        ->and($invoice->effectiveStatus())->toBe('paid');
});

test('paying a cancelled invoice throws and changes nothing', function () {
    Event::fake([InvoicePaid::class]);
    $invoice = lifecycleInvoice(['status' => 'cancelled']);

    expect(fn () => $invoice->markPaid('midtrans'))->toThrow(InvalidInvoiceTransition::class);
    expect($invoice->fresh()->status)->toBe('cancelled');
    Event::assertNotDispatched(InvoicePaid::class);
});

test('paying notifies the tenant, broadcasts PaymentConfirmed and is audited', function () {
    Notification::fake();
    Event::fake([PaymentConfirmed::class]);
    $invoice = lifecycleInvoice();

    $invoice->markPaid('midtrans');

    Notification::assertSentTo($invoice->tenant, PaymentConfirmedNotification::class);
    Event::assertDispatched(PaymentConfirmed::class, fn ($e) => $e->invoice['status'] === 'paid');
    expect(AuditLog::where('model_id', $invoice->id)->where('action', 'update')->exists())->toBeTrue();
});

// ─── Overdue is derived ───────────────────────────────────────────────────────

test('an invoice is unpaid on its due date and overdue from the next billing day', function () {
    $invoice = lifecycleInvoice(['due_date' => '2026-06-10']);

    expect($invoice->effectiveStatus())->toBe('unpaid');

    // 16:59 UTC on the 10th is 23:59 in Jakarta: still the due date.
    $this->travelTo(Carbon::parse('2026-06-10 16:59:00', 'UTC'));
    expect($invoice->effectiveStatus())->toBe('unpaid');

    // 17:00 UTC on the 10th is midnight on the 11th in Jakarta: overdue,
    // even though it is still the 10th in UTC.
    $this->travelTo(Carbon::parse('2026-06-10 17:00:00', 'UTC'));
    expect($invoice->effectiveStatus())->toBe('overdue')
        ->and($invoice->isOutstanding())->toBeTrue()
        ->and($invoice->isPayable())->toBeTrue();
});

test('paid and cancelled invoices are never overdue', function () {
    expect(lifecycleInvoice(['status' => 'paid', 'due_date' => '2026-05-01'])->effectiveStatus())->toBe('paid')
        ->and(lifecycleInvoice(['status' => 'cancelled', 'due_date' => '2026-05-01'])->effectiveStatus())->toBe('cancelled');
});

test('scopes partition invoices by effective status', function () {
    $unpaid    = lifecycleInvoice(['due_date' => '2026-06-10']);
    $overdue   = lifecycleInvoice(['due_date' => '2026-06-09']);
    $paid      = lifecycleInvoice(['status' => 'paid', 'due_date' => '2026-06-01']);
    $cancelled = lifecycleInvoice(['status' => 'cancelled', 'due_date' => '2026-06-01']);

    $ids = fn ($query) => $query->pluck('id')->sort()->values()->all();
    $sorted = fn (...$invoices) => collect($invoices)->pluck('id')->sort()->values()->all();

    expect($ids(Invoice::unpaid()))->toBe($sorted($unpaid))
        ->and($ids(Invoice::overdue()))->toBe($sorted($overdue))
        ->and($ids(Invoice::outstanding()))->toBe($sorted($unpaid, $overdue))
        ->and($ids(Invoice::paid()))->toBe($sorted($paid))
        ->and($ids(Invoice::notCancelled()))->toBe($sorted($unpaid, $overdue, $paid))
        ->and($ids(Invoice::withEffectiveStatus('overdue')))->toBe($sorted($overdue))
        ->and($ids(Invoice::withEffectiveStatus('cancelled')))->toBe($sorted($cancelled))
        ->and(Invoice::withEffectiveStatus('bogus')->count())->toBe(0);
});

// ─── API shape ────────────────────────────────────────────────────────────────

test('the API returns the effective status and payability flags', function () {
    $overdue = lifecycleInvoice(['due_date' => '2026-06-01']);
    $paid    = lifecycleInvoice(['status' => 'paid', 'paid_at' => now()]);

    $this->actingAs(lifecycleAdmin())
        ->getJson("/api/v1/invoices/{$overdue->id}")
        ->assertJsonPath('data.status', 'overdue')
        ->assertJsonPath('data.is_outstanding', true)
        ->assertJsonPath('data.is_payable', true);

    $this->actingAs(lifecycleAdmin())
        ->getJson("/api/v1/invoices/{$paid->id}")
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.is_outstanding', false)
        ->assertJsonPath('data.is_payable', false);
});

test('the unpaid filter excludes overdue invoices', function () {
    lifecycleInvoice(['due_date' => '2026-06-20']);
    lifecycleInvoice(['due_date' => '2026-06-01']);

    $this->actingAs(lifecycleAdmin())
        ->getJson('/api/v1/invoices?status=unpaid')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'unpaid');
});

test('the monthly command no longer writes overdue', function () {
    Bus::fake();
    lifecycleInvoice(['billing_month' => '2026-05', 'due_date' => '2026-05-05']);

    // Also issues June for that contract, due on the 5th: already past due on the 10th.
    $this->artisan('invoices:generate --month=2026-06')->assertExitCode(0);

    expect(Invoice::count())->toBe(2)
        ->and(Invoice::where('status', 'overdue')->count())->toBe(0)
        ->and(Invoice::overdue()->count())->toBe(2);
});
