<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public readonly Ticket $ticket)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $ticketUrl = config('app.url') . "/tickets/{$this->ticket->id}";
        $statusLabel = strtoupper(str_replace('_', ' ', $this->ticket->status));

        return (new MailMessage)
            ->subject("Status Tiket Anda Diperbarui: {$this->ticket->ticket_number}")
            ->greeting("Halo, {$this->ticket->submittedBy->name}!")
            ->line("Status tiket keluhan Anda dengan nomor *{$this->ticket->ticket_number}* telah diperbarui.")
            ->line("Detail Pembaruan:")
            ->line("- Judul Tiket: {$this->ticket->title}")
            ->line("- Status Baru: {$statusLabel}")
            ->action('Lihat Detail Tiket', $ticketUrl)
            ->line('Jika Anda memiliki pertanyaan tambahan, silakan kirimkan komentar langsung pada tiket tersebut.')
            ->line('Terima kasih telah menggunakan PropTrack!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $statusLabel = strtoupper(str_replace('_', ' ', $this->ticket->status));

        return [
            'title' => 'Status Tiket Diperbarui',
            'message' => "Tiket {$this->ticket->ticket_number} Anda telah diubah statusnya menjadi {$statusLabel}",
            'ticket_id' => $this->ticket->id,
        ];
    }
}
