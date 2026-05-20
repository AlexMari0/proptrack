<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);
});

// ─── Helpers ───────────────────────────────────────────────────────────────────

function makeOwnerUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function makeAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function makeTenantUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function tenantPayload(array $overrides = []): array
{
    return array_merge([
        'name'                    => 'Ani Wijaya',
        'email'                   => 'ani@example.com',
        'phone'                   => '081234567890',
        'id_card_number'          => '3171234567890001',
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ], $overrides);
}

function createTenant(array $overrides = []): Tenant
{
    return Tenant::create(tenantPayload($overrides));
}

// ─── Auth ──────────────────────────────────────────────────────────────────────

test('unauthenticated user cannot access tenants', function () {
    $this->getJson('/api/v1/tenants')->assertStatus(401);
});

test('tenant role user cannot list tenants', function () {
    $user = makeTenantUser();
    $this->actingAs($user)->getJson('/api/v1/tenants')->assertStatus(403);
});

// ─── Index ─────────────────────────────────────────────────────────────────────

test('owner can list tenants', function () {
    $owner = makeOwnerUser();
    createTenant();
    createTenant(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'id_card_number' => '3171234567890002']);

    $this->actingAs($owner)
        ->getJson('/api/v1/tenants')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'last_page', 'per_page'], 'message'])
        ->assertJsonPath('meta.total', 2);
});

test('tenants can be searched by name', function () {
    $owner = makeOwnerUser();
    createTenant(['name' => 'Ani Wijaya']);
    createTenant(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'id_card_number' => '3171234567890002']);

    $this->actingAs($owner)
        ->getJson('/api/v1/tenants?search=Ani')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

test('tenants can be searched by email', function () {
    $owner = makeOwnerUser();
    createTenant(['email' => 'ani@example.com']);
    createTenant(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'id_card_number' => '3171234567890002']);

    $this->actingAs($owner)
        ->getJson('/api/v1/tenants?search=budi')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

// ─── Create ────────────────────────────────────────────────────────────────────

test('owner can create a tenant', function () {
    $owner = makeOwnerUser();

    $this->actingAs($owner)
        ->postJson('/api/v1/tenants', tenantPayload())
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'id_card_number', 'emergency_contact_name', 'emergency_contact_phone', 'active_contract'], 'message'])
        ->assertJsonPath('data.name', 'Ani Wijaya')
        ->assertJsonPath('data.active_contract', null);

    $this->assertDatabaseHas('tenants', ['email' => 'ani@example.com']);
});

test('tenant role user cannot create a tenant', function () {
    $user = makeTenantUser();
    $this->actingAs($user)->postJson('/api/v1/tenants', tenantPayload())->assertStatus(403);
});

test('creating tenant fails with invalid KTP (not 16 digits)', function () {
    $owner = makeOwnerUser();

    $this->actingAs($owner)
        ->postJson('/api/v1/tenants', tenantPayload(['id_card_number' => '12345']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_card_number']);
});

test('creating tenant fails with non-numeric KTP', function () {
    $owner = makeOwnerUser();

    $this->actingAs($owner)
        ->postJson('/api/v1/tenants', tenantPayload(['id_card_number' => 'ABCD1234EFGH5678']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_card_number']);
});

test('creating tenant fails with duplicate email', function () {
    $owner = makeOwnerUser();
    createTenant();

    $this->actingAs($owner)
        ->postJson('/api/v1/tenants', tenantPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('creating tenant fails with missing required fields', function () {
    $owner = makeOwnerUser();

    $this->actingAs($owner)
        ->postJson('/api/v1/tenants', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'id_card_number', 'emergency_contact_name', 'emergency_contact_phone']);
});

// ─── Show ──────────────────────────────────────────────────────────────────────

test('owner can view a tenant', function () {
    $owner = makeOwnerUser();
    $tenant = createTenant();

    $this->actingAs($owner)
        ->getJson("/api/v1/tenants/{$tenant->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.name', 'Ani Wijaya');
});

test('tenant role user cannot view tenant details', function () {
    $user = makeTenantUser();
    $tenant = createTenant();

    $this->actingAs($user)
        ->getJson("/api/v1/tenants/{$tenant->id}")
        ->assertStatus(403);
});

test('returns 404 for non-existent tenant', function () {
    $owner = makeOwnerUser();

    $this->actingAs($owner)
        ->getJson('/api/v1/tenants/non-existent-uuid')
        ->assertStatus(404);
});

// ─── Update ────────────────────────────────────────────────────────────────────

test('owner can update a tenant', function () {
    $owner = makeOwnerUser();
    $tenant = createTenant();

    $this->actingAs($owner)
        ->putJson("/api/v1/tenants/{$tenant->id}", tenantPayload(['name' => 'Ani Wijaya Updated']))
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Ani Wijaya Updated');

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Ani Wijaya Updated']);
});

test('tenant can update keeping their own email', function () {
    $owner = makeOwnerUser();
    $tenant = createTenant();

    // Should NOT fail unique check on same email
    $this->actingAs($owner)
        ->putJson("/api/v1/tenants/{$tenant->id}", tenantPayload(['name' => 'Updated Name']))
        ->assertStatus(200);
});

test('tenant role cannot update a tenant', function () {
    $user = makeTenantUser();
    $tenant = createTenant();

    $this->actingAs($user)
        ->putJson("/api/v1/tenants/{$tenant->id}", tenantPayload())
        ->assertStatus(403);
});

// ─── Delete ────────────────────────────────────────────────────────────────────

test('admin can delete a tenant', function () {
    $admin = makeAdminUser();
    $tenant = createTenant();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/tenants/{$tenant->id}")
        ->assertStatus(200)
        ->assertJsonPath('message', 'Tenant deleted successfully');

    $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
});

test('owner cannot delete a tenant', function () {
    $owner = makeOwnerUser();
    $tenant = createTenant();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/tenants/{$tenant->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
});
