<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles (database is refreshed for every test)
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'owner']);
    Role::create(['name' => 'agent']);
    Role::create(['name' => 'tenant']);

    // Use a fake disk so no real files are written during tests
    Storage::fake('public');
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');
    return $user;
}

function makeAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function makeTenant(): User
{
    $user = User::factory()->create();
    $user->assignRole('tenant');
    return $user;
}

function propertyPayload(array $overrides = []): array
{
    return array_merge([
        'name'          => 'Kos Harmoni',
        'address'       => 'Jl. Harmoni No. 12, Jakarta Pusat',
        'type'          => 'kos',
        'status'        => 'available',
        'latitude'      => -6.1751,
        'longitude'     => 106.8272,
        'description'   => 'Kos strategis dekat MRT',
        'monthly_price' => 1500000,
    ], $overrides);
}

function createProperty(User $owner, array $overrides = []): Property
{
    return Property::create(array_merge(propertyPayload(), ['owner_id' => $owner->id], $overrides));
}

// ─── Authentication ────────────────────────────────────────────────────────────

test('unauthenticated user cannot access properties', function () {
    $this->getJson('/api/v1/properties')->assertStatus(401);
});

// ─── Index / List ──────────────────────────────────────────────────────────────

test('authenticated user can list properties', function () {
    $owner = makeOwner();
    createProperty($owner);
    createProperty($owner, ['name' => 'Apartemen Sudirman', 'type' => 'apartment']);

    $response = $this
        ->actingAs($owner)
        ->getJson('/api/v1/properties');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'message',
        ])
        ->assertJsonPath('meta.total', 2);
});

test('properties can be filtered by status', function () {
    $owner = makeOwner();
    createProperty($owner, ['status' => 'available']);
    createProperty($owner, ['status' => 'occupied']);

    $response = $this
        ->actingAs($owner)
        ->getJson('/api/v1/properties?status=occupied');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

test('properties can be filtered by type', function () {
    $owner = makeOwner();
    createProperty($owner, ['type' => 'kos']);
    createProperty($owner, ['type' => 'apartment']);
    createProperty($owner, ['type' => 'ruko']);

    $response = $this
        ->actingAs($owner)
        ->getJson('/api/v1/properties?type=kos');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

test('properties can be searched by name', function () {
    $owner = makeOwner();
    createProperty($owner, ['name' => 'Kos Harmoni Jakarta']);
    createProperty($owner, [
        'name'    => 'Apartemen Sudirman',
        'address' => 'Jl. Sudirman No. 99, Jakarta Selatan',
    ]);

    $response = $this
        ->actingAs($owner)
        ->getJson('/api/v1/properties?search=Harmoni');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('owner can create a property', function () {
    $owner = makeOwner();

    $response = $this
        ->actingAs($owner)
        ->postJson('/api/v1/properties', propertyPayload());

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'address', 'type', 'status', 'latitude', 'longitude', 'monthly_price', 'owner', 'photos'],
            'message',
        ])
        ->assertJsonPath('data.name', 'Kos Harmoni')
        ->assertJsonPath('data.owner.id', $owner->id)
        ->assertJsonPath('message', 'Property created successfully');

    $this->assertDatabaseHas('properties', ['name' => 'Kos Harmoni', 'owner_id' => $owner->id]);
});

test('admin can create a property', function () {
    $admin = makeAdmin();

    $response = $this
        ->actingAs($admin)
        ->postJson('/api/v1/properties', propertyPayload());

    $response->assertStatus(201);
});

test('tenant cannot create a property', function () {
    $tenant = makeTenant();

    $response = $this
        ->actingAs($tenant)
        ->postJson('/api/v1/properties', propertyPayload());

    $response->assertStatus(403);
});

test('creating a property fails with invalid data', function () {
    $owner = makeOwner();

    $response = $this
        ->actingAs($owner)
        ->postJson('/api/v1/properties', [
            'name'          => '',           // required
            'type'          => 'villa',      // not in enum
            'latitude'      => 999,          // out of range
            'monthly_price' => -100,         // min 0
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'address', 'type', 'latitude', 'monthly_price']);
});

// ─── Show ─────────────────────────────────────────────────────────────────────

test('any authenticated user can view a property', function () {
    $owner = makeOwner();
    $tenant = makeTenant();
    $property = createProperty($owner);

    $response = $this
        ->actingAs($tenant)
        ->getJson("/api/v1/properties/{$property->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $property->id)
        ->assertJsonPath('data.name', $property->name);
});

test('returns 404 for non-existent property', function () {
    $owner = makeOwner();

    $this->actingAs($owner)
        ->getJson('/api/v1/properties/non-existent-uuid')
        ->assertStatus(404);
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('owner can update their own property', function () {
    $owner = makeOwner();
    $property = createProperty($owner);

    $response = $this
        ->actingAs($owner)
        ->putJson("/api/v1/properties/{$property->id}", propertyPayload([
            'name'   => 'Kos Harmoni Updated',
            'status' => 'occupied',
        ]));

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Kos Harmoni Updated')
        ->assertJsonPath('data.status', 'occupied');

    $this->assertDatabaseHas('properties', ['id' => $property->id, 'name' => 'Kos Harmoni Updated']);
});

test('admin can update any property', function () {
    $owner = makeOwner();
    $admin = makeAdmin();
    $property = createProperty($owner);

    $this->actingAs($admin)
        ->putJson("/api/v1/properties/{$property->id}", propertyPayload(['name' => 'Updated by Admin']))
        ->assertStatus(200);
});

test('owner cannot update another owners property', function () {
    $owner1 = makeOwner();
    $owner2 = makeOwner();
    $property = createProperty($owner1);

    $this->actingAs($owner2)
        ->putJson("/api/v1/properties/{$property->id}", propertyPayload(['name' => 'Hijacked']))
        ->assertStatus(403);
});

// ─── Delete ───────────────────────────────────────────────────────────────────

test('owner can delete their own property', function () {
    $owner = makeOwner();
    $property = createProperty($owner);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/properties/{$property->id}")
        ->assertStatus(200)
        ->assertJsonPath('message', 'Property deleted successfully');

    $this->assertDatabaseMissing('properties', ['id' => $property->id]);
});

test('owner cannot delete another owners property', function () {
    $owner1 = makeOwner();
    $owner2 = makeOwner();
    $property = createProperty($owner1);

    $this->actingAs($owner2)
        ->deleteJson("/api/v1/properties/{$property->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('properties', ['id' => $property->id]);
});

// ─── Photo Upload ──────────────────────────────────────────────────────────────

test('owner can upload a photo to their property', function () {
    $owner = makeOwner();
    $property = createProperty($owner);
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $response = $this
        ->actingAs($owner)
        ->postJson("/api/v1/properties/{$property->id}/photos", ['photo' => $file]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'url', 'thumbnail_url'],
            'message',
        ]);

    expect($property->fresh()->getMedia('images'))->toHaveCount(1);
});

test('tenant cannot upload a photo to a property', function () {
    $owner = makeOwner();
    $tenant = makeTenant();
    $property = createProperty($owner);
    $file = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($tenant)
        ->postJson("/api/v1/properties/{$property->id}/photos", ['photo' => $file])
        ->assertStatus(403);
});

test('photo upload validates file type and size', function () {
    $owner = makeOwner();
    $property = createProperty($owner);

    // Upload a non-image file
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($owner)
        ->postJson("/api/v1/properties/{$property->id}/photos", ['photo' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photo']);
});

// ─── Photo Delete ──────────────────────────────────────────────────────────────

test('owner can delete a photo from their property', function () {
    $owner = makeOwner();
    $property = createProperty($owner);

    // Upload a photo first
    $file = UploadedFile::fake()->image('photo.jpg');
    $property->addMedia($file)->toMediaCollection('images');

    $media = $property->getFirstMedia('images');

    $this->actingAs($owner)
        ->deleteJson("/api/v1/properties/{$property->id}/photos/{$media->id}")
        ->assertStatus(200)
        ->assertJsonPath('message', 'Photo deleted successfully');

    expect($property->fresh()->getMedia('images'))->toHaveCount(0);
});

test('deleting a non-existent photo returns 404', function () {
    $owner = makeOwner();
    $property = createProperty($owner);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/properties/{$property->id}/photos/99999")
        ->assertStatus(404);
});
