<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        // Admin User
        $admin = User::factory()->create([
            'name' => 'PropTrack Admin',
            'email' => 'admin@proptrack.com',
        ]);
        $admin->assignRole('admin');

        // Property Owner User
        $owner = User::factory()->create([
            'name' => 'Property Owner',
            'email' => 'owner@proptrack.com',
        ]);
        $owner->assignRole('owner');

        // Agent User
        $agent = User::factory()->create([
            'name' => 'Support Agent',
            'email' => 'agent@proptrack.com',
        ]);
        $agent->assignRole('agent');

        // Tenant User
        $tenant = User::factory()->create([
            'name' => 'Tenant Resident',
            'email' => 'tenant@proptrack.com',
        ]);
        $tenant->assignRole('tenant');
    }
}
