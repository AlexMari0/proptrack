<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'tenant']);
    }

    /** Visibility is defined once, in Invoice::visibleTo(). */
    public function view(User $user, Invoice $invoice): bool
    {
        return Invoice::visibleTo($user)->whereKey($invoice->getKey())->exists();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['owner', 'admin']) && $this->view($user, $invoice);
    }
}
