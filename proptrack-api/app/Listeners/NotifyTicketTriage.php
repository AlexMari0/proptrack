<?php

namespace App\Listeners;

use App\Events\TicketOpened;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use Illuminate\Support\Facades\Notification;

/** A new Ticket goes to the staff who triage it: every Agent and Admin. */
class NotifyTicketTriage
{
    public function handle(TicketOpened $event): void
    {
        Notification::send(User::role(['agent', 'admin'])->get(), new NewTicketNotification($event->ticket));
    }
}
