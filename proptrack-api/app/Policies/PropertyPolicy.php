<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Property $property): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Property $property): bool
    {
        return $user->id === $property->owner_id || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Property $property): bool
    {
        return $user->id === $property->owner_id || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can upload photos to the model.
     */
    public function uploadPhoto(User $user, Property $property): bool
    {
        return $user->id === $property->owner_id || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete photos from the model.
     */
    public function deletePhoto(User $user, Property $property): bool
    {
        return $user->id === $property->owner_id || $user->hasRole('admin');
    }
}
