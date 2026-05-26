<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed Spatie roles since database is refreshed on each test
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);
});

test('admin can register any role successfully', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/auth/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'owner'
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'roles']
            ],
            'message'
        ])
        ->assertJsonPath('data.user.name', 'Budi Santoso')
        ->assertJsonPath('data.user.email', 'budi@example.com')
        ->assertJsonPath('data.user.roles', ['owner']);

    $this->assertDatabaseHas('users', [
        'email' => 'budi@example.com'
    ]);

    $user = User::where('email', 'budi@example.com')->first();
    expect($user->hasRole('owner'))->toBeTrue();
});

test('owner can register agent successfully', function () {
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    $response = $this->actingAs($owner)->postJson('/api/v1/auth/register', [
        'name' => 'Support Agent Staff',
        'email' => 'staff@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'agent'
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.user.roles', ['agent']);
});

test('owner cannot register admin role', function () {
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    $response = $this->actingAs($owner)->postJson('/api/v1/auth/register', [
        'name' => 'Another Admin',
        'email' => 'admin2@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin'
    ]);

    $response->assertStatus(403);
});

test('tenant and agent cannot register users', function () {
    $tenantUser = User::factory()->create();
    $tenantUser->assignRole('tenant');

    $response = $this->actingAs($tenantUser)->postJson('/api/v1/auth/register', [
        'name' => 'Subtenant',
        'email' => 'sub@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'tenant'
    ]);
    $response->assertStatus(403);

    $agentUser = User::factory()->create();
    $agentUser->assignRole('agent');

    $response = $this->actingAs($agentUser)->postJson('/api/v1/auth/register', [
        'name' => 'Subagent',
        'email' => 'subagent@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'agent'
    ]);
    $response->assertStatus(403);
});

test('unauthenticated guests cannot register', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Guest User',
        'email' => 'guest@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'owner'
    ]);

    $response->assertStatus(401);
});

test('registration validation fails for invalid data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123',
        'password_confirmation' => 'abc',
        'role' => 'invalid-role'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
});

test('user can login successfully with valid credentials', function () {
    $user = User::create([
        'name' => 'Ahmad Rizal',
        'email' => 'ahmad@example.com',
        'password' => Hash::make('password123')
    ]);
    $user->assignRole('tenant');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmad@example.com',
        'password' => 'password123'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'roles']
            ],
            'message'
        ])
        ->assertJsonPath('data.user.email', 'ahmad@example.com')
        ->assertJsonPath('data.user.roles', ['tenant']);
});

test('login fails with invalid credentials', function () {
    $user = User::create([
        'name' => 'Ahmad Rizal',
        'email' => 'ahmad@example.com',
        'password' => Hash::make('password123')
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmad@example.com',
        'password' => 'wrong-password'
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Invalid credentials');
});

test('authenticated user can retrieve their profile', function () {
    $user = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => Hash::make('password123')
    ]);
    $user->assignRole('owner');

    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $user->id,
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'roles' => ['owner']
            ],
            'message' => 'Success'
        ]);
});

test('unauthenticated user cannot retrieve profile', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

test('user can logout successfully', function () {
    $user = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => Hash::make('password123')
    ]);
    $user->assignRole('owner');

    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Logged out successfully');

    $this->assertCount(0, $user->fresh()->tokens);
});

test('authenticated user can update profile details and password', function () {
    $user = User::create([
        'name' => 'Ahmad Rizal',
        'email' => 'ahmad@example.com',
        'password' => Hash::make('password123')
    ]);
    $user->assignRole('owner');

    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/auth/profile', [
            'name' => 'Ahmad Rizal Updated',
            'email' => 'ahmad.new@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Ahmad Rizal Updated')
        ->assertJsonPath('data.email', 'ahmad.new@example.com');

    $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
});

test('updating profile validates inputs correctly', function () {
    $user = User::create([
        'name' => 'Ahmad Rizal',
        'email' => 'ahmad@example.com',
        'password' => Hash::make('password123')
    ]);
    $user->assignRole('owner');

    // Create another user to test email uniqueness
    User::create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => Hash::make('password123')
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    // Test validation failures (empty name, invalid email, email exists, password mismatch)
    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/auth/profile', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => 'mismatch',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);

    // Test unique email validation
    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/auth/profile', [
            'name' => 'Ahmad Rizal',
            'email' => 'other@example.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('tenant email update synchronizes with tenant profile email', function () {
    $user = User::create([
        'name' => 'Tenant User',
        'email' => 'tenant@example.com',
        'password' => Hash::make('password123')
    ]);
    $user->assignRole('tenant');

    \App\Models\Tenant::create([
        'name' => 'Tenant User',
        'email' => 'tenant@example.com',
        'phone' => '081234567891',
        'id_card_number' => '3171123456789012',
        'emergency_contact_name' => 'Emergency Name',
        'emergency_contact_phone' => '081234567890',
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->putJson('/api/v1/auth/profile', [
            'name' => 'Updated Tenant Name',
            'email' => 'new-tenant@example.com',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Tenant Name')
        ->assertJsonPath('data.email', 'new-tenant@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'new-tenant@example.com',
    ]);

    $this->assertDatabaseHas('tenants', [
        'email' => 'new-tenant@example.com',
    ]);
});
