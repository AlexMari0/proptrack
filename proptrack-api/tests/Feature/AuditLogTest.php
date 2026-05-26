<?php

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Tenant;
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

// ─── Unique Helpers ──────────────────────────────────────────────────────────

function auditAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function auditOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function auditTenant(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('unauthenticated user cannot access audit logs', function () {
    $this->getJson('/api/v1/audit-logs')
        ->assertStatus(401);
});

test('tenant and agent role cannot access audit logs', function () {
    $this->actingAs(auditTenant())
        ->getJson('/api/v1/audit-logs')
        ->assertStatus(403);
});

test('admin and owner can view audit logs index', function () {
    $admin = auditAdmin();
    $owner = auditOwner();

    // Create a property as admin to generate a log
    $this->actingAs($admin);
    $property = Property::create([
        'name'          => 'Unit A',
        'address'       => 'Jl. Harmoni 1',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 1500000,
        'owner_id'      => $owner->id,
    ]);

    // Admin should access logs
    $this->actingAs($admin)
        ->getJson('/api/v1/audit-logs')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'create')
        ->assertJsonPath('data.0.model_type', 'Property')
        ->assertJsonPath('data.0.new_values.name', 'Unit A');

    // Owner should also be authorized to access logs
    $this->actingAs($owner)
        ->getJson('/api/v1/audit-logs')
        ->assertStatus(200);
});

test('creating a property automatically logs audit entry', function () {
    $admin = auditAdmin();
    $owner = auditOwner();

    $this->actingAs($admin);
    $property = Property::create([
        'name'          => 'Villa Sentosa',
        'address'       => 'Jl. Melati 10',
        'type'          => 'ruko',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 5000000,
        'owner_id'      => $owner->id,
    ]);

    $log = AuditLog::where('model_id', $property->id)->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('create');
    expect($log->user_id)->toBe($admin->id);
    expect($log->model_type)->toBe(Property::class);
    expect($log->new_values['name'])->toBe('Villa Sentosa');
});

test('updating property details logs edit with old vs new diff values', function () {
    $admin = auditAdmin();
    $owner = auditOwner();

    $this->actingAs($admin);
    $property = Property::create([
        'name'          => 'Shophouse B',
        'address'       => 'Jl. Riau 5',
        'type'          => 'ruko',
        'status'        => 'available',
        'latitude'      => -6.175,
        'longitude'     => 106.827,
        'monthly_price' => 3000000,
        'owner_id'      => $owner->id,
    ]);

    // Clear previous logs for clarity
    AuditLog::truncate();

    // Perform update
    $property->update([
        'name'          => 'Shophouse B Refined',
        'monthly_price' => 3500000,
    ]);

    $log = AuditLog::first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('update');
    expect($log->user_id)->toBe($admin->id);
    expect($log->old_values['name'])->toBe('Shophouse B');
    expect($log->new_values['name'])->toBe('Shophouse B Refined');
    expect($log->old_values['monthly_price'])->toBe(3000000);
    expect($log->new_values['monthly_price'])->toBe(3500000);
});

test('deleting a tenant logs delete action audit entry', function () {
    $admin = auditAdmin();
    $this->actingAs($admin);

    $tenant = Tenant::create([
        'name'                    => 'Rian Pratama',
        'email'                   => 'rian@gmail.com',
        'phone'                   => '081234567890',
        'id_card_number'          => '3273123456789012',
        'emergency_contact_name'  => 'Ibu Sari',
        'emergency_contact_phone' => '081298765432',
    ]);

    // Clear previous logs
    AuditLog::truncate();

    // Perform deletion
    $tenant->delete();

    $log = AuditLog::first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('delete');
    expect($log->user_id)->toBe($admin->id);
    expect($log->model_type)->toBe(Tenant::class);
    expect($log->old_values['name'])->toBe('Rian Pratama');
    expect($log->old_values['email'])->toBe('rian@gmail.com');
});
