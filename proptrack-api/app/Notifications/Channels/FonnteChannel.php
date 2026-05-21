<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFonnte')) {
            return;
        }

        $message = $notification->toFonnte($notifiable);
        $target = $notifiable->routeNotificationFor('fonnte', $notification);

        if (! $target && method_exists($notifiable, 'routeNotificationForFonnte')) {
            $target = $notifiable->routeNotificationForFonnte($notification);
        }

        if (! $target) {
            Log::warning('Fonnte WhatsApp notification skipped: target phone number could not be resolved.', [
                'notifiable_id' => $notifiable->id ?? null,
            ]);
            return;
        }

        $token = config('services.fonnte.token');

        if (! $token) {
            Log::warning('Fonnte WhatsApp notification skipped: FONNTE_TOKEN is not configured.');
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post('https://api.fonnte.com/send', [
            'target' => $target,
            'message' => $message,
        ]);

        if ($response->failed()) {
            Log::error('Fonnte WhatsApp notification delivery failed', [
                'target'   => $target,
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);
        } else {
            Log::info("Fonnte WhatsApp notification sent successfully to {$target}");
        }
    }
}
