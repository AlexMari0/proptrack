<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $invoiceUrl = config('app.url') . "/invoices/{$this->invoice->id}";

        return (new MailMessage)
            ->subject("Pembayaran Diterima: {$this->invoice->invoice_number}")
            ->greeting("Halo, {$this->invoice->tenant->name}!")
            ->line("Terima kasih. Kami telah menerima pembayaran Anda untuk tagihan {$this->invoice->invoice_number} terkait sewa properti {$this->invoice->property->name}.")
            ->line("Detail Transaksi:")
            ->line("- Nomor Invoice: {$this->invoice->invoice_number}")
            ->line("- Jumlah Pembayaran: Rp " . number_format($this->invoice->amount, 0, ',', '.'))
            ->line("- Tanggal Bayar: " . date('d F Y H:i', strtotime($this->invoice->paid_at)))
            ->line("- Metode Pembayaran: " . strtoupper($this->invoice->payment_gateway ?? 'Midtrans'))
            ->action('Lihat Detail Invoice', $invoiceUrl)
            ->line('Status tagihan Anda sekarang adalah PAID (Lunas).')
            ->line('Terima kasih atas kerja sama Anda!');
    }
}
