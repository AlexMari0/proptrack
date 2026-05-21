<?php

use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
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

// ─── Test Helpers with Unique Prefix ─────────────────────────────────────────

function ticketOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function ticketAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function ticketAgent(): User
{
    $user = User::factory()->create();
    $user->assignRole('agent');
    return $user;
}

function ticketTenantUser(string $email = 'tenant@example.com'): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole('tenant');
    return $user;
}

function ticketMakeTenant(string $email = 'tenant@example.com'): Tenant
{
    return Tenant::create([
        'name'                    => 'Ani Wijaya',
        'email'                   => $email,
        'phone'                   => '081234567890',
        'id_card_number'          => fake()->numerify('################'),
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);
}

function ticketMakeProperty(User $owner): Property
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

function ticketMakeContract(Tenant $tenant, Property $property, string $status = 'active'): Contract
{
    return Contract::create([
        'tenant_id'      => $tenant->id,
        'property_id'    => $property->id,
        'start_date'     => now()->subMonth(),
        'end_date'       => now()->addYear(),
        'monthly_rent'   => 1500000,
        'deposit_amount' => 3000000,
        'billing_date'   => 5,
        'status'         => $status,
    ]);
}

function ticketMakeTicket(Property $property, User $submitter, array $overrides = []): Ticket
{
    $year = now()->year;
    $seq = fake()->unique()->numberBetween(1, 999);
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

test('unauthenticated user cannot access tickets', function () {
    $this->getJson('/api/v1/tickets')->assertStatus(401);
});

test('tenant can submit ticket with active contract', function () {
    $email = 'active@tenant.com';
    $tenantUser = ticketTenantUser($email);
    $tenant = ticketMakeTenant($email);
    $owner = ticketOwner();
    $property = ticketMakeProperty($owner);
    ticketMakeContract($tenant, $property);

    $this->actingAs($tenantUser)
        ->postJson('/api/v1/tickets', [
            'property_id' => $property->id,
            'category'    => 'maintenance',
            'priority'    => 'high',
            'title'       => 'Pipa Pecah',
            'description' => 'Air meluap di kamar mandi.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Pipa Pecah')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.submitted_by.name', $tenantUser->name)
        ->assertJsonPath('data.property.name', $property->name);
});

test('tenant cannot submit ticket without active contract', function () {
    $email = 'inactive@tenant.com';
    $tenantUser = ticketTenantUser($email);
    ticketMakeTenant($email); // profile created but no contract
    $owner = ticketOwner();
    $property = ticketMakeProperty($owner);

    $this->actingAs($tenantUser)
        ->postJson('/api/v1/tickets', [
            'property_id' => $property->id,
            'category'    => 'maintenance',
            'priority'    => 'high',
            'title'       => 'Pipa Pecah',
            'description' => 'Air meluap.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['property_id']);
});

test('tenant can list only their own tickets', function () {
    $tenant1Email = 't1@tenant.com';
    $t1User = ticketTenantUser($tenant1Email);
    $t1 = ticketMakeTenant($tenant1Email);

    $tenant2Email = 't2@tenant.com';
    $t2User = ticketTenantUser($tenant2Email);
    $t2 = ticketMakeTenant($tenant2Email);

    $owner = ticketOwner();
    $property1 = ticketMakeProperty($owner);
    $property2 = ticketMakeProperty($owner);

    ticketMakeContract($t1, $property1);
    ticketMakeContract($t2, $property2);

    ticketMakeTicket($property1, $t1User);
    ticketMakeTicket($property2, $t2User);

    $this->actingAs($t1User)
        ->getJson('/api/v1/tickets')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.submitted_by.id', $t1User->id);
});

test('owner can view tickets of properties they own', function () {
    $owner1 = ticketOwner();
    $owner2 = ticketOwner();

    $tenantEmail = 't@tenant.com';
    $tenantUser = ticketTenantUser($tenantEmail);
    $tenant = ticketMakeTenant($tenantEmail);

    $property1 = ticketMakeProperty($owner1);
    $property2 = ticketMakeProperty($owner2);

    ticketMakeContract($tenant, $property1);
    ticketMakeContract($tenant, $property2);

    $ticket1 = ticketMakeTicket($property1, $tenantUser);
    $ticket2 = ticketMakeTicket($property2, $tenantUser);

    // Owner 1 can list owned property tickets
    $this->actingAs($owner1)
        ->getJson('/api/v1/tickets')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ticket1->id);

    // Owner 1 cannot view Ticket 2 (other owner property)
    $this->actingAs($owner1)
        ->getJson("/api/v1/tickets/{$ticket2->id}")
        ->assertStatus(403);
});

test('admin and agent can list all tickets', function () {
    $owner = ticketOwner();
    $tenantEmail = 't@tenant.com';
    $tenantUser = ticketTenantUser($tenantEmail);
    $tenant = ticketMakeTenant($tenantEmail);
    $property = ticketMakeProperty($owner);
    ticketMakeContract($tenant, $property);

    ticketMakeTicket($property, $tenantUser);
    ticketMakeTicket($property, $tenantUser);

    $admin = ticketAdmin();
    $agent = ticketAgent();

    $this->actingAs($admin)
        ->getJson('/api/v1/tickets')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $this->actingAs($agent)
        ->getJson('/api/v1/tickets')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('agent can update ticket status and assignment', function () {
    $owner = ticketOwner();
    $tenantEmail = 't@tenant.com';
    $tenantUser = ticketTenantUser($tenantEmail);
    $tenant = ticketMakeTenant($tenantEmail);
    $property = ticketMakeProperty($owner);
    ticketMakeContract($tenant, $property);
    $ticket = ticketMakeTicket($property, $tenantUser);

    $agent = ticketAgent();

    $this->actingAs($agent)
        ->putJson("/api/v1/tickets/{$ticket->id}/status", [
            'status'         => 'in_progress',
            'assigned_to_id' => $agent->id,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.assigned_to.id', $agent->id);
});

test('tenant and owner cannot update ticket status', function () {
    $owner = ticketOwner();
    $tenantEmail = 't@tenant.com';
    $tenantUser = ticketTenantUser($tenantEmail);
    $tenant = ticketMakeTenant($tenantEmail);
    $property = ticketMakeProperty($owner);
    ticketMakeContract($tenant, $property);
    $ticket = ticketMakeTicket($property, $tenantUser);

    $this->actingAs($tenantUser)
        ->putJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
        ])
        ->assertStatus(403);

    $this->actingAs($owner)
        ->putJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
        ])
        ->assertStatus(403);
});

test('authorized users can submit comments', function () {
    $owner = ticketOwner();
    $tenantEmail = 't@tenant.com';
    $tenantUser = ticketTenantUser($tenantEmail);
    $tenant = ticketMakeTenant($tenantEmail);
    $property = ticketMakeProperty($owner);
    ticketMakeContract($tenant, $property);
    $ticket = ticketMakeTicket($property, $tenantUser);

    // Tenant can comment
    $this->actingAs($tenantUser)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Kapan akan diperbaiki?',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.content', 'Kapan akan diperbaiki?')
        ->assertJsonPath('data.user.role', 'tenant');

    // Owner can comment
    $this->actingAs($owner)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Saya hubungi tukang hari ini.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.content', 'Saya hubungi tukang hari ini.')
        ->assertJsonPath('data.user.role', 'owner');

    // Unauthorized owner cannot comment
    $otherOwner = ticketOwner();
    $this->actingAs($otherOwner)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Spam comment.',
        ])
        ->assertStatus(403);
});
