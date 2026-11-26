<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\Property;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin') || $user->hasRole('tenant');
    }

    /** Visibility is defined once, in Contract::visibleTo(). */
    public function view(User $user, Contract $contract): bool
    {
        return Contract::visibleTo($user)->whereKey($contract->getKey())->exists();
    }

    /**
     * Owners create contracts only on their own properties; admins on any.
     */
    public function create(User $user, ?Property $property = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('owner')
            && ($property === null || $property->owner_id === $user->id);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->hasAnyRole(['owner', 'admin']) && $this->view($user, $contract);
    }

    public function terminate(User $user, Contract $contract): bool
    {
        return $user->hasAnyRole(['owner', 'admin']) && $this->view($user, $contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasRole('admin');
    }
}
