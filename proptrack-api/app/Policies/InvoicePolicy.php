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

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        if ($user->hasRole('tenant')) {
            return $invoice->tenant?->email === $user->email;
        }

        return false;
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['owner', 'admin']);
    }
}
