<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    public function terminate(User $user, Contract $contract): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasRole('admin');
    }
}
