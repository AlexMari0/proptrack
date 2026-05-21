<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleMidtransWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 10;

    public function __construct(private readonly array $payload)
    {
    }

    public function handle(PaymentService $paymentService): void
    {
        $orderIdRaw     = $this->payload['order_id']        ?? '';
        $transactionStatus = $this->payload['transaction_status'] ?? '';
        $fraudStatus    = $this->payload['fraud_status']    ?? '';

        Log::info("Midtrans webhook: order={$orderIdRaw} status={$transactionStatus} fraud={$fraudStatus}");

        // Midtrans settlement patterns
        $isSuccess =
            ($transactionStatus === 'capture'    && $fraudStatus === 'accept') ||
            ($transactionStatus === 'settlement');

        if (! $isSuccess) {
            Log::info("Midtrans webhook ignored: status={$transactionStatus}");
            return;
        }

        // order_id is invoice_number (INV-YYYY-NNNN)
        $paymentService->handlePaymentSuccess($orderIdRaw, 'midtrans');
    }
}
