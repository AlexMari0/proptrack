<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
 * One visibility rule per resource (Invoice/Contract/Ticket::visibleTo):
 * the list endpoint, the detail endpoint and the realtime channel must
 * agree for every role.
 *
 *   Invoices, contracts: admin all · owner own properties · tenant own · agent none
 *   Tickets:             admin/agent all · owner own properties · tenant submitted
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'owner', 'agent', 'tenant'] as $role) {
        Role::create(['name' => $role]);
    }

    // The null broadcaster authorizes every channel; use a real driver so channel rules run.
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'dummy',
        'broadcasting.connections.pusher.secret' => 'dummy',
        'broadcasting.connections.pusher.app_id' => 'dummy',
    ]);
    require base_path('routes/channels.php');

    $make = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    };

    $this->admin  = $make('admin');
    $this->agent  = $make('agent');
    $this->ownerA = $make('owner');
    $this->ownerB = $make('owner');
    $this->tenantUserA = $make('tenant');
    $this->tenantUserB = $make('tenant');

    // One portfolio per owner, each with one tenant, contract, invoice and ticket.
    foreach (['A', 'B'] as $i => $key) {
        $owner = $this->{"owner{$key}"};
        $tenantUser = $this->{"tenantUser{$key}"};

        $property = Property::create([
            'name' => "Kos {$key}", 'address' => "Jl. {$key} No. 1", 'type' => 'kos', 'status' => 'occupied',
            'latitude' => -6.175, 'longitude' => 106.827, 'monthly_price' => 1500000, 'owner_id' => $owner->id,
        ]);
        $tenant = Tenant::create([
            'user_id' => $tenantUser->id, 'name' => "Tenant {$key}", 'email' => $tenantUser->email,
            'phone' => '081234567890', 'id_card_number' => "317123456789000{$i}",
            'emergency_contact_name' => 'Ibu Sari', 'emergency_contact_phone' => '081298765432',
        ]);
        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'property_id' => $property->id, 'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(), 'monthly_rent' => 1500000, 'deposit_amount' => 3000000,
            'billing_date' => 5, 'status' => 'active',
        ]);
        $invoice = Invoice::create([
            'contract_id' => $contract->id, 'tenant_id' => $tenant->id, 'property_id' => $property->id,
            'invoice_number' => "INV-2026-000{$i}", 'status' => 'unpaid', 'amount' => 1500000,
            'billing_month' => now()->format('Y-m'), 'due_date' => now()->addDays(5),
        ]);
        $ticket = Ticket::create([
            'ticket_number' => "TKT-2026-000{$i}", 'property_id' => $property->id, 'submitted_by_id' => $tenantUser->id,
            'category' => 'maintenance', 'priority' => 'medium', 'status' => 'open',
            'title' => 'AC Bocor', 'description' => 'AC bocor di kamar utama.',
        ]);

        $this->{"property{$key}"} = $property;
        $this->{"contract{$key}"} = $contract;
        $this->{"invoice{$key}"}  = $invoice;
        $this->{"ticket{$key}"}   = $ticket;
    }
});

/**
 * Asserts what `$user` can see of a resource, through every route that exposes it.
 *
 * @param  array<string>  $visible  keys ('A', 'B') the user should see
 */
function assertSees($test, User $user, string $resource, array $visible): void
{
    $records = ['A' => $test->{"{$resource}A"}, 'B' => $test->{"{$resource}B"}];
    $path = ['invoice' => 'invoices', 'contract' => 'contracts', 'ticket' => 'tickets'][$resource];

    $list = $test->actingAs($user)->getJson("/api/v1/{$path}");
    $listed = $list->status() === 200 ? collect($list->json('data'))->pluck('id')->all() : [];

    foreach ($records as $key => $record) {
        $shouldSee = in_array($key, $visible, true);

        expect(in_array($record->id, $listed, true))->toBe($shouldSee, "{$resource} {$key} in list");

        $test->actingAs($user)->getJson("/api/v1/{$path}/{$record->id}")
            ->assertStatus($shouldSee ? 200 : 403);

        if ($resource !== 'contract') {
            Sanctum::actingAs($user, ['*']);
            $test->postJson('/broadcasting/auth', [
                'channel_name' => "private-{$resource}.{$record->id}",
                'socket_id'    => '1234.1234',
            ])->assertStatus($shouldSee ? 200 : 403);
        }
    }
}

dataset('roles', [
    //            user          invoices     contracts    tickets
    'admin'   => ['admin',       ['A', 'B'],  ['A', 'B'],  ['A', 'B']],
    'owner A' => ['ownerA',      ['A'],       ['A'],       ['A']],
    'owner B' => ['ownerB',      ['B'],       ['B'],       ['B']],
    'tenant A' => ['tenantUserA', ['A'],      ['A'],       ['A']],
    'agent'   => ['agent',       [],          [],          ['A', 'B']],
]);

test('list, detail and realtime channel agree on visibility', function (string $who, array $invoices, array $contracts, array $tickets) {
    $user = $this->{$who};

    assertSees($this, $user, 'invoice', $invoices);
    assertSees($this, $user, 'contract', $contracts);
    assertSees($this, $user, 'ticket', $tickets);
})->with('roles');

test("an owner cannot act on another owner's contracts and invoices", function () {
    $this->actingAs($this->ownerA)
        ->postJson("/api/v1/contracts/{$this->contractB->id}/terminate")
        ->assertStatus(403);

    $this->actingAs($this->ownerA)
        ->postJson("/api/v1/invoices/{$this->invoiceB->id}/send")
        ->assertStatus(403);

    $this->actingAs($this->ownerA)
        ->getJson("/api/v1/invoices/{$this->invoiceB->id}/document")
        ->assertStatus(403);

    expect($this->contractB->fresh()->status)->toBe('active');
});

test("an owner cannot create a contract on another owner's property", function () {
    $tenant = Tenant::create([
        'name' => 'New Tenant', 'email' => 'new@example.com', 'phone' => '081234567890',
        'id_card_number' => '3171234567890099', 'emergency_contact_name' => 'X', 'emergency_contact_phone' => '081298765432',
    ]);
    $vacant = Property::create([
        'name' => 'Kos B2', 'address' => 'Jl. B No. 2', 'type' => 'kos', 'status' => 'available',
        'latitude' => -6.175, 'longitude' => 106.827, 'monthly_price' => 1500000, 'owner_id' => $this->ownerB->id,
    ]);

    $this->actingAs($this->ownerA)->postJson('/api/v1/contracts', [
        'tenant_id' => $tenant->id, 'property_id' => $vacant->id,
        'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addYear()->toDateString(),
        'monthly_rent' => 1500000, 'deposit_amount' => 3000000, 'billing_date' => 1,
    ])->assertStatus(403);
});

test('the financial report only counts the owner\'s own invoices', function () {
    $year = now()->year;

    $this->actingAs($this->ownerA)
        ->getJson("/api/v1/reports/financial?year={$year}")
        ->assertStatus(200)
        ->assertJsonPath('data.total_invoiced', 1500000);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/reports/financial?year={$year}")
        ->assertJsonPath('data.total_invoiced', 3000000);
});
