<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Events\NotificationSent;
use App\Events\TicketStatusUpdated;
use App\Events\PaymentConfirmed;
use App\Notifications\NewTicketNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'dummy',
        'broadcasting.connections.pusher.secret' => 'dummy',
        'broadcasting.connections.pusher.app_id' => 'dummy',
    ]);
    require base_path('routes/channels.php');
});

// ─── Test Helpers with Unique Prefix 'broadcasting' ────────────────────────

function broadcastingMakeRole(string $name)
{
    return Role::findOrCreate($name, 'web');
}

function broadcastingAdmin()
{
    broadcastingMakeRole('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function broadcastingAgent()
{
    broadcastingMakeRole('agent');
    $user = User::factory()->create();
    $user->assignRole('agent');
    return $user;
}

function broadcastingOwner()
{
    broadcastingMakeRole('owner');
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function broadcastingTenantUser()
{
    broadcastingMakeRole('tenant');
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function broadcastingMakeTenant(User $user)
{
    return Tenant::create([
        'name'                    => $user->name,
        'email'                   => $user->email,
        'phone'                   => '081234567890',
        'id_card_number'          => '1234567890123456',
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);
}

function broadcastingMakeProperty(User $owner)
{
    return Property::create([
        'name'          => 'Unit A',
        'address'       => 'Street 1',
        'type'          => 'kos',
        'status'        => 'available',
        'monthly_price' => 1000000,
        'owner_id'      => $owner->id,
        'latitude'      => -6.2088,
        'longitude'     => 106.8456,
    ]);
}

function broadcastingMakeContract(Tenant $tenant, Property $property)
{
    return Contract::create([
        'tenant_id'    => $tenant->id,
        'property_id'  => $property->id,
        'start_date'   => now()->toDateString(),
        'end_date'     => now()->addYear()->toDateString(),
        'billing_date' => 5,
        'status'       => 'active',
        'monthly_rent' => 1000000,
        'deposit_amount'=> 500000,
    ]);
}

function broadcastingMakeTicket(User $tenantUser, Property $property)
{
    return Ticket::create([
        'ticket_number'   => 'TKT-2026-0001',
        'property_id'     => $property->id,
        'submitted_by_id' => $tenantUser->id,
        'category'        => 'plumbing',
        'priority'        => 'high',
        'status'          => 'open',
        'title'           => 'Broken pipe',
        'description'     => 'Water is leaking everywhere.',
    ]);
}

function broadcastingMakeInvoice(Contract $contract)
{
    return Invoice::create([
        'invoice_number'  => 'INV-2026-0001',
        'contract_id'     => $contract->id,
        'tenant_id'       => $contract->tenant_id,
        'property_id'     => $contract->property_id,
        'amount'          => 1000000,
        'billing_month'   => '2026-05',
        'due_date'        => now()->addDays(5)->toDateString(),
        'status'          => 'unpaid',
    ]);
}

// ─── Channel Access Authentication Tests ────────────────────────────────────

test('user can authorize access to their own user channel but not others', function () {
    $user1 = broadcastingTenantUser();
    $user2 = broadcastingTenantUser();

    Sanctum::actingAs($user1, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$user1->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    Sanctum::actingAs($user1, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$user2->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(403);
});

test('RBAC authorizations for ticket private channel', function () {
    $owner = broadcastingOwner();
    $tenantUser1 = broadcastingTenantUser();
    $tenantUser2 = broadcastingTenantUser();
    
    $tenant1 = broadcastingMakeTenant($tenantUser1);
    $property = broadcastingMakeProperty($owner);
    $ticket = broadcastingMakeTicket($tenantUser1, $property);

    // 1. Admin & Agent can access
    Sanctum::actingAs(broadcastingAdmin(), ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    Sanctum::actingAs(broadcastingAgent(), ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    // 2. Owner of property can access
    Sanctum::actingAs($owner, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    // 3. Submitting tenant can access
    Sanctum::actingAs($tenantUser1, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    // 4. Other tenant is forbidden
    Sanctum::actingAs($tenantUser2, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(403);
});

test('RBAC authorizations for invoice private channel', function () {
    $owner = broadcastingOwner();
    $tenantUser1 = broadcastingTenantUser();
    $tenantUser2 = broadcastingTenantUser();
    
    $tenant1 = broadcastingMakeTenant($tenantUser1);
    $property = broadcastingMakeProperty($owner);
    $contract = broadcastingMakeContract($tenant1, $property);
    $invoice = broadcastingMakeInvoice($contract);

    // 1. Admin & Agent can access
    Sanctum::actingAs(broadcastingAdmin(), ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-invoice.{$invoice->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    Sanctum::actingAs(broadcastingAgent(), ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-invoice.{$invoice->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    // 2. Owner of property can access
    Sanctum::actingAs($owner, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-invoice.{$invoice->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    // 3. Invoice tenant can access
    Sanctum::actingAs($tenantUser1, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-invoice.{$invoice->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(200);

    // 4. Other tenant is forbidden
    Sanctum::actingAs($tenantUser2, ['*']);
    $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-invoice.{$invoice->id}",
            'socket_id'    => '1234.1234',
        ])
        ->assertStatus(403);
});

// ─── Event Broadcasting Verification Tests ──────────────────────────────────

test('NotificationSent event is broadcasted when a database notification is created', function () {
    Event::fake([NotificationSent::class]);

    $owner = broadcastingOwner();
    $tenantUser = broadcastingTenantUser();
    $tenant = broadcastingMakeTenant($tenantUser);
    $property = broadcastingMakeProperty($owner);
    $ticket = broadcastingMakeTicket($tenantUser, $property);

    // Sending database notification triggers the LaravelNotificationSent listener,
    // which dispatches our NotificationSent real-time broadcast.
    $tenantUser->notify(new NewTicketNotification($ticket));

    Event::assertDispatched(NotificationSent::class, function ($event) use ($tenantUser, $ticket) {
        return (string) $event->notification['data']['ticket_id'] === (string) $ticket->id;
    });
});

test('TicketStatusUpdated event is broadcasted on ticket status update', function () {
    Event::fake([TicketStatusUpdated::class, NotificationSent::class]);

    $owner = broadcastingOwner();
    $tenantUser = broadcastingTenantUser();
    $tenant = broadcastingMakeTenant($tenantUser);
    $property = broadcastingMakeProperty($owner);
    $ticket = broadcastingMakeTicket($tenantUser, $property);

    $service = new \App\Services\TicketService();
    
    // We update the status, which should fire the TicketStatusUpdated broadcast event
    $service->updateStatus(broadcastingAgent(), $ticket, 'in_progress');

    Event::assertDispatched(TicketStatusUpdated::class, function ($event) use ($ticket) {
        return (string) $event->ticket['id'] === (string) $ticket->id 
            && $event->ticket['status'] === 'in_progress';
    });
});

test('PaymentConfirmed event is broadcasted on payment success', function () {
    Event::fake([PaymentConfirmed::class, NotificationSent::class]);

    $owner = broadcastingOwner();
    $tenantUser = broadcastingTenantUser();
    $tenant = broadcastingMakeTenant($tenantUser);
    $property = broadcastingMakeProperty($owner);
    $contract = broadcastingMakeContract($tenant, $property);
    $invoice = broadcastingMakeInvoice($contract);

    $service = new \App\Services\PaymentService();
    
    // We mark payment as success, which should fire the PaymentConfirmed broadcast event
    $service->handlePaymentSuccess($invoice->invoice_number, 'midtrans');

    Event::assertDispatched(PaymentConfirmed::class, function ($event) use ($invoice) {
        return (string) $event->invoice['id'] === (string) $invoice->id 
            && $event->invoice['status'] === 'paid';
    });
});
