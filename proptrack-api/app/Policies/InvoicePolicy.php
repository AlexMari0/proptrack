<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'agent']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'agent']);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->hasAnyRole(['owner', 'admin']);
    }
}
