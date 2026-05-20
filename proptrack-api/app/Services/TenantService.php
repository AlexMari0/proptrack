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
        return Tenant::create([
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

        return $tenant->fresh();
    }

    /**
     * Delete a tenant profile.
     */
    public function deleteTenant(Tenant $tenant): bool
    {
        $tenant->delete();
        return true;
    }
}
