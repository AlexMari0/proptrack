<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed Spatie roles since database is refreshed on each test
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);
});

test('user can register successfully and is assigned the correct role', function () {
    $response = $this->postJson('/api/v1/auth/register', [
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

test('registration validation fails for invalid data', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123',
        'password_confirmation' => 'abc',
        'role' => 'admin' // Not in owner, agent, tenant
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
