<?php

namespace App\Listeners;

use App\Events\TicketStatusChanged;
use App\Events\TicketStatusUpdated;
use App\Notifications\TicketStatusChangedNotification;

/** The submitter hears about every status change; open ticket pages update live. */
class AnnounceTicketStatusChange
{
    public function handle(TicketStatusChanged $event): void
    {
        $ticket = $event->ticket;

        $ticket->submittedBy?->notify(new TicketStatusChangedNotification($ticket));

        event(new TicketStatusUpdated($ticket));
    }
}
