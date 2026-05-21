<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\FonnteChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public readonly Invoice $invoice)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', FonnteChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $invoiceUrl = config('app.url') . "/invoices/{$this->invoice->id}";

        return (new MailMessage)
            ->subject("Tagihan Baru: {$this->invoice->invoice_number}")
            ->greeting("Halo, {$this->invoice->tenant->name}!")
            ->line("Tagihan sewa bulanan Anda untuk properti {$this->invoice->property->name} telah diterbitkan.")
            ->line("Detail Tagihan:")
            ->line("- Nomor Invoice: {$this->invoice->invoice_number}")
            ->line("- Bulan Tagihan: {$this->invoice->billing_month}")
            ->line("- Jumlah: Rp " . number_format($this->invoice->amount, 0, ',', '.'))
            ->line("- Tanggal Jatuh Tempo: " . date('d F Y', strtotime($this->invoice->due_date)))
            ->action('Bayar Sekarang', $invoiceUrl)
            ->line('Terima kasih telah menggunakan PropTrack!');
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toFonnte(object $notifiable): string
    {
        $amountFmt = "Rp " . number_format($this->invoice->amount, 0, ',', '.');
        $dueDateFmt = date('d F Y', strtotime($this->invoice->due_date));

        return "*[PropTrack] Tagihan Baru: {$this->invoice->invoice_number}*\n\n" .
            "Halo {$this->invoice->tenant->name},\n" .
            "Tagihan sewa bulanan Anda untuk properti *{$this->invoice->property->name}* telah diterbitkan.\n\n" .
            "Detail Tagihan:\n" .
            "- No. Invoice: {$this->invoice->invoice_number}\n" .
            "- Bulan: {$this->invoice->billing_month}\n" .
            "- Jumlah: {$amountFmt}\n" .
            "- Jatuh Tempo: {$dueDateFmt}\n\n" .
            "Silakan lakukan pembayaran melalui dashboard PropTrack Anda.\n" .
            "Terima kasih!";
    }
}
