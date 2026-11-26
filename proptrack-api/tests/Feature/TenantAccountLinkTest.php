<?php

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * A Tenant profile is linked to its login account by tenants.user_id.
 * Email is matched exactly once, when the profile is created (TenantService);
 * after that, changing either email never affects who can see what.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'owner', 'agent', 'tenant'] as $role) {
        Role::create(['name' => $role]);
    }
});

function linkAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function linkPayload(array $overrides = []): array
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

/** Creates a tenant through the API and gives it a contract and an invoice. */
function linkTenantWithInvoice($test): array
{
    $test->actingAs(linkAdmin())->postJson('/api/v1/tenants', linkPayload())->assertStatus(201);
    $tenant = Tenant::where('email', 'ani@example.com')->firstOrFail();

    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $property = Property::create([
        'name' => 'Kos Harmoni', 'address' => 'Jl. Harmoni No. 1', 'type' => 'kos', 'status' => 'available',
        'latitude' => -6.175, 'longitude' => 106.827, 'monthly_price' => 1500000, 'owner_id' => $owner->id,
    ]);
    $contract = Contract::create([
        'tenant_id' => $tenant->id, 'property_id' => $property->id, 'start_date' => now()->subMonth(),
        'end_date' => now()->addYear(), 'monthly_rent' => 1500000, 'deposit_amount' => 3000000,
        'billing_date' => 5, 'status' => 'active',
    ]);
    $invoice = Invoice::create([
        'contract_id' => $contract->id, 'tenant_id' => $tenant->id, 'property_id' => $property->id,
        'invoice_number' => 'INV-2026-0001', 'status' => 'unpaid', 'amount' => 1500000,
        'billing_month' => now()->format('Y-m'), 'due_date' => now()->addDays(5),
    ]);

    return [$tenant->fresh(), $invoice];
}

test('creating a tenant creates a linked tenant account', function () {
    $this->actingAs(linkAdmin())->postJson('/api/v1/tenants', linkPayload())->assertStatus(201);

    $tenant = Tenant::where('email', 'ani@example.com')->first();
    expect($tenant->user)->not->toBeNull()
        ->and($tenant->user->email)->toBe('ani@example.com')
        ->and($tenant->user->hasRole('tenant'))->toBeTrue()
        ->and($tenant->user->tenantProfile->is($tenant))->toBeTrue();
});

test('creating a tenant links a self-registered account instead of duplicating it', function () {
    $registered = User::factory()->create(['email' => 'ani@example.com']);
    $registered->assignRole('tenant');

    $this->actingAs(linkAdmin())->postJson('/api/v1/tenants', linkPayload())->assertStatus(201);

    expect(Tenant::where('email', 'ani@example.com')->first()->user_id)->toBe($registered->id)
        ->and(User::where('email', 'ani@example.com')->count())->toBe(1);
});

test('a tenant keeps access after changing their own login email', function () {
    [$tenant, $invoice] = linkTenantWithInvoice($this);
    $user = $tenant->user;

    $this->actingAs($user)
        ->putJson('/api/v1/auth/profile', ['name' => $user->name, 'email' => 'ani.new@example.com'])
        ->assertStatus(200);

    expect($tenant->fresh()->email)->toBe('ani.new@example.com');

    $this->actingAs($user->fresh())
        ->getJson('/api/v1/invoices')
        ->assertJsonPath('meta.total', 1);
    $this->actingAs($user->fresh())
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertStatus(200);
});

test('an admin changing the tenant email updates the login email and keeps access', function () {
    [$tenant, $invoice] = linkTenantWithInvoice($this);

    $this->actingAs(linkAdmin())
        ->putJson("/api/v1/tenants/{$tenant->id}", linkPayload(['email' => 'ani.work@example.com', 'name' => 'Ani W.']))
        ->assertStatus(200);

    $user = $tenant->user->fresh();
    expect($user->email)->toBe('ani.work@example.com')
        ->and($user->name)->toBe('Ani W.');

    $this->actingAs($user)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertStatus(200);
});

test('an unlinked account with the same email gets no access', function () {
    [$tenant, $invoice] = linkTenantWithInvoice($this);
    $tenant->user->update(['email' => 'moved@example.com']);

    $impostor = User::factory()->create(['email' => 'ani@example.com']);
    $impostor->assignRole('tenant');

    $this->actingAs($impostor)->getJson('/api/v1/invoices')->assertJsonPath('meta.total', 0);
    $this->actingAs($impostor)->getJson("/api/v1/invoices/{$invoice->id}")->assertStatus(403);
});

test('deleting a tenant deletes its linked account', function () {
    $this->actingAs(linkAdmin())->postJson('/api/v1/tenants', linkPayload())->assertStatus(201);
    $tenant = Tenant::where('email', 'ani@example.com')->first();
    $userId = $tenant->user_id;

    $this->actingAs(linkAdmin())->deleteJson("/api/v1/tenants/{$tenant->id}")->assertSuccessful();

    expect(User::find($userId))->toBeNull();
});

test('notifications go to the linked account, or to the profile when there is none', function () {
    $this->actingAs(linkAdmin())->postJson('/api/v1/tenants', linkPayload())->assertStatus(201);
    $linked = Tenant::where('email', 'ani@example.com')->first();

    $unlinked = Tenant::create(linkPayload(['email' => 'walkin@example.com', 'id_card_number' => '3171234567890002']));

    expect($linked->getNotifiable()->is($linked->user))->toBeTrue()
        ->and($unlinked->getNotifiable()->is($unlinked))->toBeTrue()
        ->and($linked->user->routeNotificationForFonnte())->toBe('081234567890');
});
