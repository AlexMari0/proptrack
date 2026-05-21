<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
}, ['guards' => ['sanctum']]);

// Private ticket channel for live comment threads and status changes
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\Ticket::with('property')->find($ticketId);
    if (!$ticket) {
        return false;
    }

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
}, ['guards' => ['sanctum']]);

// Private invoice channel for live checkout payment confirmations
Broadcast::channel('invoice.{invoiceId}', function ($user, $invoiceId) {
    $invoice = \App\Models\Invoice::with(['tenant', 'property'])->find($invoiceId);
    if (!$invoice) {
        return false;
    }

    if ($user->hasRole('admin') || $user->hasRole('agent')) {
        return true;
    }

    if ($user->hasRole('owner')) {
        return $invoice->property->owner_id === $user->id;
    }

    if ($user->hasRole('tenant')) {
        return $invoice->tenant->email === $user->email;
    }

    return false;
}, ['guards' => ['sanctum']]);
