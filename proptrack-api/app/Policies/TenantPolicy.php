<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Owners and admins can list all tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    /**
     * Owners and admins can view a single tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    /**
     * Owners and admins can create tenants.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    /**
     * Owners and admins can update tenants.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    /**
     * Only admins can delete tenants (data integrity).
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('admin');
    }
}
