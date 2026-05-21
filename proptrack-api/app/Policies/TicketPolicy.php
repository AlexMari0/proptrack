<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
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
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('agent')) {
            return true;
        }

        if ($user->hasRole('owner')) {
            return $ticket->property->owner_id === $user->id;
        }

        if ($user->hasRole('tenant')) {
            return $ticket->submitted_by_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the status of the model.
     */
    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin') || $user->hasRole('agent');
    }

    /**
     * Determine whether the user can add a comment to the model.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
