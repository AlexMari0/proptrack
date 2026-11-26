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
        // Visibility is defined once, in Ticket::visibleTo().
        return Ticket::visibleTo($user)->whereKey($ticket->getKey())->exists();
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
