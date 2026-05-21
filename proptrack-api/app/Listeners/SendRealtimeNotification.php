<?php

namespace App\Listeners;

use App\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSent as LaravelNotificationSent;

class SendRealtimeNotification
{
    /**
     * Handle the event.
     */
    public function handle(LaravelNotificationSent $event): void
    {
        if ($event->channel === 'database') {
            // Fetch the saved database notification model from the database.
            // Since it was just saved, we look it up by the specific notification ID to ensure exact match.
            $dbNotification = $event->notifiable->notifications()->where('id', $event->notification->id)->first();

            if ($dbNotification) {
                event(new NotificationSent($event->notifiable, $dbNotification));
            }
        }
    }
}
