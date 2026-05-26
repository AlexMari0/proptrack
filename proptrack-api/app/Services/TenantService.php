<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    /**
     * Create a new tenant profile.
     */
    public function createTenant(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name'                    => $data['name'],
            'email'                   => $data['email'],
            'phone'                   => $data['phone'],
            'id_card_number'          => $data['id_card_number'],
            'emergency_contact_name'  => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ]);

        // Automatically create User account if it doesn't already exist
        $user = \App\Models\User::where('email', $data['email'])->first();
        if (!$user) {
            $password = $data['id_card_number'] ?? 'password123'; // Use KTP as password, fallback to default
            $user = \App\Models\User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => \Illuminate\Support\Facades\Hash::make($password),
            ]);
            $user->assignRole('tenant');
        }

        return $tenant;
    }

    /**
     * Update an existing tenant's details.
     */
    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $oldEmail = $tenant->email;

        $tenant->update([
            'name'                    => $data['name'],
            'email'                   => $data['email'],
            'phone'                   => $data['phone'],
            'id_card_number'          => $data['id_card_number'],
            'emergency_contact_name'  => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ]);

        // Sync with User profile
        if ($oldEmail !== $data['email']) {
            \App\Models\User::where('email', $oldEmail)->update([
                'email' => $data['email'],
                'name' => $data['name'],
            ]);
        } else {
            \App\Models\User::where('email', $oldEmail)->update([
                'name' => $data['name'],
            ]);
        }

        return $tenant->fresh();
    }

    /**
     * Delete a tenant profile.
     */
    public function deleteTenant(Tenant $tenant): bool
    {
        // Delete corresponding user account too to keep internal system clean
        \App\Models\User::where('email', $tenant->email)->delete();

        $tenant->delete();
        return true;
    }
}
