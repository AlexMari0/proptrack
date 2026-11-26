<?php

use App\Documents\Documents;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Numbering\DocumentNumbers;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;

/*
 * Documents: PDFs are cached until the record they show changes.
 * DocumentNumbers: PREFIX-YYYY-NNNN, never reused, restarting each year.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'owner', 'agent', 'tenant'] as $role) {
        Role::create(['name' => $role]);
    }
    $this->travelTo(Carbon::parse('2026-06-10 05:00:00', 'UTC'));
    Bus::fake();
    Pdf::fake();
    Storage::fake('local');

    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->property = Property::create([
        'name' => 'Kos Harmoni', 'address' => 'Jl. Harmoni No. 1', 'type' => 'kos', 'status' => 'available',
        'latitude' => -6.175, 'longitude' => 106.827, 'monthly_price' => 1500000, 'owner_id' => $owner->id,
    ]);
    $tenant = Tenant::create([
        'name' => 'Ani Wijaya', 'email' => 'ani@example.com', 'phone' => '081234567890',
        'id_card_number' => '3171234567890001', 'emergency_contact_name' => 'Ibu Sari', 'emergency_contact_phone' => '081298765432',
    ]);
    $this->contract = Contract::create([
        'tenant_id' => $tenant->id, 'property_id' => $this->property->id, 'start_date' => '2026-01-01',
        'end_date' => '2027-01-01', 'monthly_rent' => 1500000, 'deposit_amount' => 3000000,
        'billing_date' => 5, 'status' => 'active',
    ]);
});

/** Pdf::fake() does not write files; stand in for the rendered PDF at that path. */
function documentsRendered(string $absolutePath): void
{
    file_put_contents($absolutePath, '%PDF-1.4 rendered');
}

function documentsRenderCount(): int
{
    $count = 0;
    try {
        Pdf::assertSaved(function () use (&$count) {
            $count++;
            return false;
        });
    } catch (\PHPUnit\Framework\AssertionFailedError) {
        // assertSaved fails when nothing matched; we only wanted the count.
    }

    return $count;
}

// ─── Documents ────────────────────────────────────────────────────────────────

test('the invoice PDF shows the effective status', function (string $dueDate, string $status, string $label) {
    $invoice = Invoice::issueFor($this->contract, '2026-06');
    $invoice->forceFill(['due_date' => $dueDate])->save();
    if ($status === 'paid') {
        $invoice->markPaid('midtrans');
    }

    app(Documents::class)->invoice($invoice->fresh());

    Pdf::assertSee($label);
})->with([
    'unpaid'  => ['2026-06-20', 'unpaid', 'Belum Dibayar'],
    'overdue' => ['2026-06-05', 'unpaid', 'Jatuh Tempo'],
    'paid'    => ['2026-06-20', 'paid', 'LUNAS'],
]);

test('a cached invoice PDF is reused until the invoice changes, then replaced', function () {
    $invoice = Invoice::issueFor($this->contract, '2026-06');
    $invoice->forceFill(['due_date' => '2026-06-20'])->save();
    $documents = app(Documents::class);

    $first = $documents->invoice($invoice);
    documentsRendered($first);
    expect($documents->invoice($invoice))->toBe($first)
        ->and(documentsRenderCount())->toBe(1);

    $this->travel(5)->seconds();
    $invoice->markPaid('midtrans');

    $afterPayment = $documents->invoice($invoice->fresh());
    expect($afterPayment)->not->toBe($first)
        ->and(file_exists($first))->toBeFalse()
        ->and(documentsRenderCount())->toBe(2);
});

test('an invoice PDF is re-rendered when the invoice becomes overdue', function () {
    $invoice = Invoice::issueFor($this->contract, '2026-06');
    $invoice->forceFill(['due_date' => '2026-06-10'])->save();
    $documents = app(Documents::class);

    documentsRendered($onDueDate = $documents->invoice($invoice));

    $this->travelTo(Carbon::parse('2026-06-11 05:00:00', 'UTC'));

    expect($documents->invoice($invoice->fresh()))->not->toBe($onDueDate);
});

test('the contract PDF is replaced after the contract changes', function () {
    $documents = app(Documents::class);
    documentsRendered($before = $documents->contract($this->contract));

    $this->travel(5)->seconds();
    $this->contract->update(['monthly_rent' => 1750000]);

    expect($documents->contract($this->contract->fresh()))->not->toBe($before);
    Pdf::assertViewIs('pdf.contract');
});

// ─── DocumentNumbers ──────────────────────────────────────────────────────────

test('invoice numbers are never reused after a delete', function () {
    $first  = Invoice::issueFor($this->contract, '2026-04');
    $second = Invoice::issueFor($this->contract, '2026-05');
    $first->delete();

    $third = Invoice::issueFor($this->contract, '2026-06');

    expect([$first->invoice_number, $second->invoice_number, $third->invoice_number])
        ->toBe(['INV-2026-0001', 'INV-2026-0002', 'INV-2026-0003']);
});

test('numbers restart each year and are counted per prefix', function () {
    $numbers = app(DocumentNumbers::class);

    expect($numbers->next(DocumentNumbers::INVOICE))->toBe('INV-2026-0001')
        ->and($numbers->next(DocumentNumbers::TICKET))->toBe('TKT-2026-0001')
        ->and($numbers->next(DocumentNumbers::INVOICE))->toBe('INV-2026-0002');

    $this->travelTo(Carbon::parse('2027-01-01 05:00:00', 'UTC'));

    expect($numbers->next(DocumentNumbers::INVOICE))->toBe('INV-2027-0001');
});

test('ticket numbers are never reused after a delete', function () {
    $tenantUser = User::factory()->create();
    $tenantUser->assignRole('tenant');
    $this->contract->tenant->update(['user_id' => $tenantUser->id]);

    $file = fn () => $this->actingAs($tenantUser)->postJson('/api/v1/tickets', [
        'property_id' => $this->property->id, 'category' => 'maintenance', 'priority' => 'medium',
        'title' => 'AC Bocor', 'description' => 'AC bocor di kamar utama.',
    ])->assertStatus(201)->json('data.ticket_number');

    $first = $file();
    $second = $file();
    Ticket::where('ticket_number', $second)->delete();

    expect([$first, $second, $file()])->toBe(['TKT-2026-0001', 'TKT-2026-0002', 'TKT-2026-0003']);
});
