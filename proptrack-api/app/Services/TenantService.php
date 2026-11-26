<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TenantService
{
    /**
     * Create a new tenant profile.
     */
    public function createTenant(array $data): Tenant
    {
        // Link to the account with this email (e.g. a tenant who self-registered),
        // or create one. After this, the link is tenants.user_id, never email.
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['id_card_number'] ?? 'password123'), // KTP as initial password
            ]);
            $user->assignRole('tenant');
        }

        return Tenant::create([
            'user_id'                 => $user->id,
            'name'                    => $data['name'],
            'email'                   => $data['email'],
            'phone'                   => $data['phone'],
            'id_card_number'          => $data['id_card_number'],
            'emergency_contact_name'  => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ]);
    }

    /**
     * Update an existing tenant's details.
     */
    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $tenant->update([
            'name'                    => $data['name'],
            'email'                   => $data['email'],
            'phone'                   => $data['phone'],
            'id_card_number'          => $data['id_card_number'],
            'emergency_contact_name'  => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ]);

        // Keep the linked account's name and login email in step with the profile.
        $tenant->user?->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);

        return $tenant->fresh();
    }

    /**
     * Delete a tenant profile.
     */
    public function deleteTenant(Tenant $tenant): bool
    {
        // Delete the linked login account too to keep internal system clean
        $tenant->user?->delete();

        $tenant->delete();
        return true;
    }
}
