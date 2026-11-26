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

// Private ticket channel for live comment threads and status changes.
// Channel access is the same rule as viewing the record (TicketPolicy / InvoicePolicy).
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\Ticket::find($ticketId);

    return $ticket !== null && $user->can('view', $ticket);
}, ['guards' => ['sanctum']]);

// Private invoice channel for live checkout payment confirmations
Broadcast::channel('invoice.{invoiceId}', function ($user, $invoiceId) {
    $invoice = \App\Models\Invoice::find($invoiceId);

    return $invoice !== null && $user->can('view', $invoice);
}, ['guards' => ['sanctum']]);
