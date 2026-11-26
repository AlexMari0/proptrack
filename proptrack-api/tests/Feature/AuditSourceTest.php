<?php

use App\Events\NotificationSent;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

/*
 * Every audit entry names its actor: a logged-in user (user_id), or a
 * non-user source such as a payment gateway or a scheduled command.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'owner', 'agent', 'tenant'] as $role) {
        Role::create(['name' => $role]);
    }

    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->owner = $owner;

    $this->property = Property::create([
        'name' => 'Kos Harmoni', 'address' => 'Jl. Harmoni No. 1', 'type' => 'kos', 'status' => 'available',
        'latitude' => -6.175, 'longitude' => 106.827, 'monthly_price' => 1500000, 'owner_id' => $owner->id,
    ]);
    $tenant = Tenant::create([
        'name' => 'Ani Wijaya', 'email' => 'ani@example.com', 'phone' => '081234567890',
        'id_card_number' => '3171234567890001', 'emergency_contact_name' => 'Ibu Sari', 'emergency_contact_phone' => '081298765432',
    ]);
    $this->contract = Contract::create([
        'tenant_id' => $tenant->id, 'property_id' => $this->property->id, 'start_date' => now()->subMonth(),
        'end_date' => now()->addYear(), 'monthly_rent' => 1500000, 'deposit_amount' => 3000000,
        'billing_date' => 5, 'status' => 'active',
    ]);
});

function auditEntriesFor(string $modelId)
{
    return AuditLog::where('model_id', $modelId)->orderBy('created_at')->get();
}

test('a logged-in user is the actor, with no source', function () {
    $this->actingAs($this->owner)
        ->putJson("/api/v1/properties/{$this->property->id}", [
            'name' => 'Kos Harmoni Baru', 'address' => 'Jl. Harmoni No. 1', 'type' => 'kos', 'status' => 'available',
            'latitude' => -6.175, 'longitude' => 106.827, 'monthly_price' => 1500000,
        ])->assertSuccessful();

    $entry = auditEntriesFor($this->property->id)->last();
    expect($entry->user_id)->toBe($this->owner->id)
        ->and($entry->source)->toBeNull();
});

test('invoices issued by the monthly command are attributed to it', function () {
    Bus::fake();

    $this->artisan('invoices:generate --month=' . now()->format('Y-m'))->assertExitCode(0);

    $invoice = Invoice::firstOrFail();
    $entry = auditEntriesFor($invoice->id)->first();
    expect($entry->action)->toBe('create')
        ->and($entry->user_id)->toBeNull()
        ->and($entry->source)->toBe('system:invoices:generate');
});

test('a settlement from the gateway webhook is attributed to the gateway', function () {
    $invoice = Invoice::issueFor($this->contract, now()->format('Y-m'));
    $payload = [
        'order_id' => $invoice->invoice_number, 'status_code' => '200',
        'gross_amount' => '1500000.00', 'transaction_status' => 'settlement',
    ];
    $payload['signature_key'] = hash('sha512', $payload['order_id'] . '200' . '1500000.00' . config('services.midtrans.server_key'));

    $this->postJson('/api/v1/payments/webhook/midtrans', $payload)->assertStatus(200);

    $entry = auditEntriesFor($invoice->id)->firstWhere('action', 'update');
    expect($entry->new_values['status'])->toBe('paid')
        ->and($entry->user_id)->toBeNull()
        ->and($entry->source)->toBe('gateway:midtrans');
});

test('the audit log API exposes the source', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Bus::fake();
    $this->artisan('invoices:generate --month=' . now()->format('Y-m'));

    $this->actingAs($admin)
        ->getJson('/api/v1/audit-logs')
        ->assertStatus(200)
        ->assertJsonFragment(['source' => 'system:invoices:generate']);
});

test('a database notification is pushed to the browser once', function () {
    Event::fake([NotificationSent::class]);
    $tenantUser = User::factory()->create();
    $tenantUser->assignRole('tenant');
    $ticket = Ticket::create([
        'ticket_number' => 'TKT-2026-0001', 'property_id' => $this->property->id, 'submitted_by_id' => $tenantUser->id,
        'category' => 'maintenance', 'priority' => 'medium', 'status' => 'open', 'title' => 'AC', 'description' => 'AC bocor',
    ]);

    $tenantUser->notify(new TicketStatusChangedNotification($ticket));

    Event::assertDispatchedTimes(NotificationSent::class, 1);
});
