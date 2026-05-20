<?php

use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use App\Jobs\GenerateContractPdfJob;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);
});

// ─── Helpers ───────────────────────────────────────────────────────────────────

function contractOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function contractAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function contractTenantUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function contractMakeTenant(array $overrides = []): Tenant
{
    return Tenant::create(array_merge([
        'name'                    => 'Ani Wijaya',
        'email'                   => fake()->unique()->safeEmail(),
        'phone'                   => '081234567890',
        'id_card_number'          => fake()->numerify('################'),
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ], $overrides));
}

function contractMakeProperty(array $overrides = []): Property
{
    $propertyOwner = User::factory()->create();
    $propertyOwner->assignRole('owner');

    return Property::create(array_merge([
        'name'          => 'Kos Harmoni ' . fake()->unique()->numberBetween(1, 9999),
        'address'       => 'Jl. Harmoni No. 1',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 1500000,
        'owner_id'      => $propertyOwner->id,
    ], $overrides));
}

function contractPayload(string $tenantId, string $propertyId, array $overrides = []): array
{
    return array_merge([
        'tenant_id'      => $tenantId,
        'property_id'    => $propertyId,
        'start_date'     => now()->addDay()->toDateString(),
        'end_date'       => now()->addYear()->toDateString(),
        'monthly_rent'   => 1500000,
        'deposit_amount' => 3000000,
        'billing_date'   => 1,
    ], $overrides);
}

function makeContract(string $tenantId, string $propertyId, array $overrides = []): Contract
{
    return Contract::create(array_merge([
        'tenant_id'      => $tenantId,
        'property_id'    => $propertyId,
        'start_date'     => now()->addDay(),
        'end_date'       => now()->addYear(),
        'monthly_rent'   => 1500000,
        'deposit_amount' => 3000000,
        'billing_date'   => 1,
        'status'         => 'active',
    ], $overrides));
}

// ─── Auth ──────────────────────────────────────────────────────────────────────

test('unauthenticated user cannot access contracts', function () {
    $this->getJson('/api/v1/contracts')->assertStatus(401);
});

test('tenant role cannot list contracts', function () {
    $this->actingAs(contractTenantUser())->getJson('/api/v1/contracts')->assertStatus(403);
});

// ─── Index ─────────────────────────────────────────────────────────────────────

test('owner can list contracts', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();
    makeContract($tenant->id, $property->id);

    $this->actingAs($owner)
        ->getJson('/api/v1/contracts')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page'], 'message'])
        ->assertJsonPath('meta.total', 1);
});

test('contracts can be filtered by status', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant1 = contractMakeTenant();
    $tenant2 = contractMakeTenant();
    $property1 = contractMakeProperty();
    $property2 = contractMakeProperty();

    makeContract($tenant1->id, $property1->id, ['status' => 'active']);
    makeContract($tenant2->id, $property2->id, ['status' => 'terminated', 'terminated_at' => now()]);

    $this->actingAs($owner)
        ->getJson('/api/v1/contracts?status=active')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

// ─── Create ────────────────────────────────────────────────────────────────────

test('owner can create a contract and job is dispatched', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();

    $this->actingAs($owner)
        ->postJson('/api/v1/contracts', contractPayload($tenant->id, $property->id))
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'status', 'tenant', 'property', 'monthly_rent', 'billing_date'], 'message'])
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.tenant.name', 'Ani Wijaya')
        ->assertJsonPath('data.billing_date', 1);

    Bus::assertDispatched(GenerateContractPdfJob::class);
    $this->assertDatabaseHas('contracts', ['tenant_id' => $tenant->id, 'status' => 'active']);
});

test('tenant role cannot create a contract', function () {
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();

    $this->actingAs(contractTenantUser())
        ->postJson('/api/v1/contracts', contractPayload($tenant->id, $property->id))
        ->assertStatus(403);
});

test('cannot create contract if property already has an active contract', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant1 = contractMakeTenant();
    $tenant2 = contractMakeTenant();
    $property = contractMakeProperty();

    makeContract($tenant1->id, $property->id); // first active contract

    $this->actingAs($owner)
        ->postJson('/api/v1/contracts', contractPayload($tenant2->id, $property->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['property_id']);
});

test('validation rejects end_date before start_date', function () {
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();

    $this->actingAs($owner)
        ->postJson('/api/v1/contracts', contractPayload($tenant->id, $property->id, [
            'start_date' => now()->addYear()->toDateString(),
            'end_date'   => now()->addDay()->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

test('validation rejects billing_date outside 1-28', function () {
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();

    $this->actingAs($owner)
        ->postJson('/api/v1/contracts', contractPayload($tenant->id, $property->id, ['billing_date' => 31]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['billing_date']);
});

// ─── Show ──────────────────────────────────────────────────────────────────────

test('owner can view a contract', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();
    $contract = makeContract($tenant->id, $property->id);

    $this->actingAs($owner)
        ->getJson("/api/v1/contracts/{$contract->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $contract->id);
});

// ─── Update ────────────────────────────────────────────────────────────────────

test('owner can update an active contract', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();
    $contract = makeContract($tenant->id, $property->id);

    $this->actingAs($owner)
        ->putJson("/api/v1/contracts/{$contract->id}", [
            'start_date'     => now()->addDay()->toDateString(),
            'end_date'       => now()->addYears(2)->toDateString(),
            'monthly_rent'   => 2000000,
            'deposit_amount' => 4000000,
            'billing_date'   => 5,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.monthly_rent', 2000000);
});

test('cannot update a terminated contract', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();
    $contract = makeContract($tenant->id, $property->id, ['status' => 'terminated', 'terminated_at' => now()]);

    $this->actingAs($owner)
        ->putJson("/api/v1/contracts/{$contract->id}", [
            'start_date'     => now()->addDay()->toDateString(),
            'end_date'       => now()->addYear()->toDateString(),
            'monthly_rent'   => 2000000,
            'deposit_amount' => 4000000,
            'billing_date'   => 1,
        ])
        ->assertStatus(422);
});

// ─── Terminate ─────────────────────────────────────────────────────────────────

test('owner can terminate an active contract', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();
    $contract = makeContract($tenant->id, $property->id);

    $this->actingAs($owner)
        ->postJson("/api/v1/contracts/{$contract->id}/terminate")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'terminated')
        ->assertJsonPath('message', 'Contract terminated successfully');

    $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'status' => 'terminated']);
});

test('cannot terminate an already terminated contract', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant = contractMakeTenant();
    $property = contractMakeProperty();
    $contract = makeContract($tenant->id, $property->id, ['status' => 'terminated', 'terminated_at' => now()]);

    $this->actingAs($owner)
        ->postJson("/api/v1/contracts/{$contract->id}/terminate")
        ->assertStatus(422);
});

// ─── Property can get a new contract after termination ─────────────────────────

test('property can have new active contract after previous is terminated', function () {
    Bus::fake();
    $owner = contractOwner();
    $tenant1 = contractMakeTenant();
    $tenant2 = contractMakeTenant();
    $property = contractMakeProperty();

    $first = makeContract($tenant1->id, $property->id);

    // Terminate first
    $this->actingAs($owner)->postJson("/api/v1/contracts/{$first->id}/terminate")->assertStatus(200);

    // Now create second
    $this->actingAs($owner)
        ->postJson('/api/v1/contracts', contractPayload($tenant2->id, $property->id))
        ->assertStatus(201);

    Bus::assertDispatchedTimes(GenerateContractPdfJob::class, 1);
});
