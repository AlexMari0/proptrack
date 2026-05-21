<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ContractExpiringNotification;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\InvoiceDueNotification;
use App\Notifications\NewTicketNotification;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'owner']);
    Role::firstOrCreate(['name' => 'agent']);
    Role::firstOrCreate(['name' => 'tenant']);
});

// ─── Test Helpers with Unique Prefix ─────────────────────────────────────────

function notifOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function notifAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function notifAgent(): User
{
    $user = User::factory()->create();
    $user->assignRole('agent');
    return $user;
}

function notifTenantUser(string $email = 'tenant@example.com'): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole('tenant');
    return $user;
}

function notifMakeTenant(string $email = 'tenant@example.com'): Tenant
{
    return Tenant::create([
        'name'                    => 'Budi Utomo',
        'email'                   => $email,
        'phone'                   => '081234567890',
        'id_card_number'          => fake()->numerify('################'),
        'emergency_contact_name'  => 'Ibu Budi',
        'emergency_contact_phone' => '081298765432',
    ]);
}

function notifMakeProperty(User $owner): Property
{
    return Property::create([
        'name'          => 'Apartemen Kemang ' . fake()->unique()->numberBetween(1, 9999),
        'address'       => 'Jl. Kemang Raya No. 12',
        'type'          => 'apartment',
        'status'        => 'available',
        'latitude'      => -6.25,
        'longitude'     => 106.81,
        'monthly_price' => 5000000,
        'owner_id'      => $owner->id,
    ]);
}

function notifMakeContract(Tenant $tenant, Property $property, string $status = 'active', array $overrides = []): Contract
{
    return Contract::create(array_merge([
        'tenant_id'      => $tenant->id,
        'property_id'    => $property->id,
        'start_date'     => now()->subMonth(),
        'end_date'       => now()->addYear(),
        'monthly_rent'   => 5000000,
        'deposit_amount' => 10000000,
        'billing_date'   => 5,
        'status'         => $status,
    ], $overrides));
}

function notifMakeTicket(Property $property, User $submitter, array $overrides = []): Ticket
{
    $year = now()->year;
    $seq = fake()->unique()->numberBetween(1, 9999);
    return Ticket::create(array_merge([
        'ticket_number'   => sprintf('TKT-%d-%04d', $year, $seq),
        'property_id'     => $property->id,
        'submitted_by_id' => $submitter->id,
        'category'        => 'maintenance',
        'priority'        => 'medium',
        'status'          => 'open',
        'title'           => 'AC Bocor',
        'description'     => 'AC bocor di kamar utama.',
    ], $overrides));
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('unauthenticated user cannot access notifications list', function () {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
});

test('user can retrieve notifications list and unread count', function () {
    $user = notifTenantUser();
    $owner = notifOwner();
    $property = notifMakeProperty($owner);

    // Trigger two in-app database notifications manually for testing
    $ticket = notifMakeTicket($property, $user, [
        'ticket_number'   => 'TKT-2026-9999',
    ]);

    $user->notify(new NewTicketNotification($ticket));
    $user->notify(new TicketStatusChangedNotification($ticket));

    $this->actingAs($user)
        ->getJson('/api/v1/notifications')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.unread_count', 2)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'title',
                    'message',
                    'data',
                    'read_at',
                    'created_at',
                ],
            ],
            'meta' => [
                'unread_count',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
            'message',
        ]);
});

test('user can mark single notification as read', function () {
    $user = notifTenantUser();
    $owner = notifOwner();
    $property = notifMakeProperty($owner);
    $ticket = notifMakeTicket($property, $user, [
        'ticket_number'   => 'TKT-2026-8888',
    ]);

    $user->notify(new NewTicketNotification($ticket));
    $notif = $user->unreadNotifications->first();

    $this->actingAs($user)
        ->putJson("/api/v1/notifications/{$notif->id}/read")
        ->assertStatus(200);

    expect($notif->fresh()->read())->toBeTrue();
});

test('user can mark all notifications as read', function () {
    $user = notifTenantUser();
    $owner = notifOwner();
    $property = notifMakeProperty($owner);
    $ticket = notifMakeTicket($property, $user, [
        'ticket_number'   => 'TKT-2026-7777',
    ]);

    $user->notify(new NewTicketNotification($ticket));
    $user->notify(new TicketStatusChangedNotification($ticket));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->putJson('/api/v1/notifications/read-all')
        ->assertStatus(200);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('ticket creation dispatches NewTicketNotification to agents and admins', function () {
    Notification::fake();

    $email = 'active@tenant.com';
    $tenantUser = notifTenantUser($email);
    $tenant = notifMakeTenant($email);
    $owner = notifOwner();
    $property = notifMakeProperty($owner);
    notifMakeContract($tenant, $property);

    $admin = notifAdmin();
    $agent = notifAgent();

    $this->actingAs($tenantUser)
        ->postJson('/api/v1/tickets', [
            'property_id' => $property->id,
            'category'    => 'maintenance',
            'priority'    => 'high',
            'title'       => 'Pintu Rusak',
            'description' => 'Pintu tidak bisa ditutup.',
        ])
        ->assertStatus(201);

    Notification::assertSentTo(
        [$admin, $agent],
        NewTicketNotification::class
    );
});

test('ticket status update dispatches TicketStatusChangedNotification to submitting tenant', function () {
    Notification::fake();

    $email = 'tenant@test.com';
    $tenantUser = notifTenantUser($email);
    $tenant = notifMakeTenant($email);
    $owner = notifOwner();
    $property = notifMakeProperty($owner);
    notifMakeContract($tenant, $property);

    $ticket = Ticket::create([
        'ticket_number'   => 'TKT-2026-6666',
        'property_id'     => $property->id,
        'submitted_by_id' => $tenantUser->id,
        'category'        => 'maintenance',
        'priority'        => 'low',
        'status'          => 'open',
        'title'           => 'Lampu Mati',
        'description'     => 'Lampu dapur mati.',
    ]);

    $agent = notifAgent();

    $this->actingAs($agent)
        ->putJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'in_progress',
        ])
        ->assertStatus(200);

    Notification::assertSentTo(
        $tenantUser,
        TicketStatusChangedNotification::class
    );
});

test('manual invoice send triggers InvoiceCreatedNotification', function () {
    Notification::fake();

    $email = 'invoice@tenant.com';
    $tenantUser = notifTenantUser($email);
    $tenant = notifMakeTenant($email);
    $owner = notifOwner();
    $property = notifMakeProperty($owner);
    $contract = notifMakeContract($tenant, $property);

    $invoice = Invoice::create([
        'contract_id'    => $contract->id,
        'tenant_id'      => $tenant->id,
        'property_id'    => $property->id,
        'invoice_number' => 'INV-2026-0001',
        'status'         => 'unpaid',
        'amount'         => 5000000,
        'billing_month'  => '2026-05',
        'due_date'       => now()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/invoices/{$invoice->id}/send")
        ->assertStatus(200);

    Notification::assertSentTo(
        $tenantUser,
        InvoiceCreatedNotification::class
    );
});

test('scheduled command dispatches InvoiceDueNotification and ContractExpiringNotification', function () {
    Notification::fake();

    $tenant1Email = 't1@expiring.com';
    $tenant1User = notifTenantUser($tenant1Email);
    $tenant1 = notifMakeTenant($tenant1Email);

    $tenant2Email = 't2@due.com';
    $tenant2User = notifTenantUser($tenant2Email);
    $tenant2 = notifMakeTenant($tenant2Email);

    $owner = notifOwner();
    $property1 = notifMakeProperty($owner);
    $property2 = notifMakeProperty($owner);

    // Contract expiring in exactly 30 days
    $contractExpiring = notifMakeContract($tenant1, $property1, 'active', [
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    // Unpaid invoice due in exactly 3 days
    $contract2 = notifMakeContract($tenant2, $property2);
    $invoiceDue = Invoice::create([
        'contract_id'    => $contract2->id,
        'tenant_id'      => $tenant2->id,
        'property_id'    => $property2->id,
        'invoice_number' => 'INV-2026-0002',
        'status'         => 'unpaid',
        'amount'         => 5000000,
        'billing_month'  => '2026-06',
        'due_date'       => today()->addDays(3)->toDateString(),
    ]);

    // Execute CLI command
    $this->artisan('notifications:send-scheduled')
        ->assertExitCode(0);

    // Assert Expiring Contract notifications sent to BOTH tenant and owner
    Notification::assertSentTo(
        $tenant1User,
        ContractExpiringNotification::class,
        function ($notification) {
            return $notification->recipientType === 'tenant';
        }
    );

    Notification::assertSentTo(
        $owner,
        ContractExpiringNotification::class,
        function ($notification) {
            return $notification->recipientType === 'owner';
        }
    );

    // Assert Due Invoice notification sent to tenant
    Notification::assertSentTo(
        $tenant2User,
        InvoiceDueNotification::class
    );
});
