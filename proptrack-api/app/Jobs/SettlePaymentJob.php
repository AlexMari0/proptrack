<?php

namespace App\Jobs;

use App\Payments\PaymentNotification;
use App\Payments\Payments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Queues settlement so provider webhooks get a fast 200 and retries on failure. */
class SettlePaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $backoff = 10;

    /** @param array<string, mixed> $notification PaymentNotification::toArray() */
    public function __construct(public readonly array $notification)
    {
    }

    public function handle(Payments $payments): void
    {
        $payments->settle(PaymentNotification::fromArray($this->notification));
    }
}
