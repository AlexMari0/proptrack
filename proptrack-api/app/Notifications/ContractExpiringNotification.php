<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Contract  $contract
     * @param  string  $recipientType  Can be 'tenant' or 'owner'
     */
    public function __construct(
        public readonly Contract $contract,
        public readonly string $recipientType
    ) {
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
        $contractUrl = config('app.url') . "/contracts/{$this->contract->id}";
        $endDateFmt = date('d F Y', strtotime($this->contract->end_date));

        if ($this->recipientType === 'owner') {
            return (new MailMessage)
                ->subject("Pengingat: Kontrak Sewa Properti {$this->contract->property->name} Berakhir 30 Hari Lagi")
                ->greeting("Halo, {$this->contract->property->owner->name}!")
                ->line("Kami ingin menginformasikan bahwa kontrak sewa untuk properti Anda, *{$this->contract->property->name}*, dengan tenant *{$this->contract->tenant->name}* akan berakhir dalam waktu 30 hari pada tanggal {$endDateFmt}.")
                ->line("Detail Kontrak:")
                ->line("- Nama Penyewa: {$this->contract->tenant->name}")
                ->line("- Selesai Kontrak: {$endDateFmt}")
                ->action('Lihat Detail Kontrak', $contractUrl)
                ->line('Anda dapat menghubungi penyewa untuk mendiskusikan perpanjangan sewa atau mempersiapkan properti untuk penyewa berikutnya.')
                ->line('Terima kasih telah mempercayakan pengelolaan properti Anda kepada PropTrack!');
        }

        // Default to tenant recipient representation
        return (new MailMessage)
            ->subject("Pengingat: Kontrak Sewa Properti {$this->contract->property->name} Berakhir 30 Hari Lagi")
            ->greeting("Halo, {$this->contract->tenant->name}!")
            ->line("Kami ingin mengingatkan bahwa kontrak sewa Anda untuk properti *{$this->contract->property->name}* akan berakhir dalam waktu 30 hari pada tanggal {$endDateFmt}.")
            ->line("Detail Kontrak:")
            ->line("- Properti: {$this->contract->property->name}")
            ->line("- Selesai Kontrak: {$endDateFmt}")
            ->action('Lihat Detail Kontrak', $contractUrl)
            ->line('Jika Anda ingin memperpanjang masa sewa, mohon segera hubungi pemilik properti atau agen pengelola.')
            ->line('Terima kasih telah tinggal bersama kami di PropTrack!');
    }
}
