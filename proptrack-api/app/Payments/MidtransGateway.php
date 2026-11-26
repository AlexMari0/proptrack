<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Midtrans Snap. Notifications are signed with
 * sha512(order_id + status_code + gross_amount + server_key).
 */
class MidtransGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $serverKey,
        private readonly bool $production,
    ) {
    }

    public function name(): string
    {
        return 'midtrans';
    }

    public function startCheckout(Invoice $invoice): Checkout
    {
        $invoice->loadMissing(['tenant', 'property']);

        $response = Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->post($this->baseUrl() . '/transactions', [
                'transaction_details' => [
                    'order_id'     => $invoice->invoice_number,
                    'gross_amount' => $invoice->amount,
                ],
                'customer_details' => [
                    'first_name' => $invoice->tenant->name,
                    'email'      => $invoice->tenant->email,
                    'phone'      => $invoice->tenant->phone ?? '',
                ],
                'item_details' => [[
                    'id'       => $invoice->id,
                    'price'    => $invoice->amount,
                    'quantity' => 1,
                    'name'     => "Sewa - {$invoice->property->name} ({$invoice->billing_month})",
                ]],
            ]);

        if ($response->failed()) {
            Log::error('Midtrans transaction creation failed', [
                'invoice_number' => $invoice->invoice_number,
                'status'         => $response->status(),
                'body'           => $response->json(),
            ]);

            throw new PaymentGatewayError('Midtrans refused the transaction.');
        }

        return new Checkout($response->json('token'), $response->json('redirect_url'));
    }

    public function parseNotification(array $payload): ?PaymentNotification
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '') .
            ($payload['status_code'] ?? '') .
            ($payload['gross_amount'] ?? '') .
            $this->serverKey
        );

        if (! is_string($payload['signature_key'] ?? null) || ! hash_equals($expected, $payload['signature_key'])) {
            return null;
        }

        return new PaymentNotification(
            invoiceNumber: (string) $payload['order_id'],
            outcome: $this->outcome($payload['transaction_status'] ?? '', $payload['fraud_status'] ?? ''),
            amount: (int) round((float) ($payload['gross_amount'] ?? 0)),
            gateway: $this->name(),
        );
    }

    /**
     * capture+accept (cards) and settlement mean the money arrived;
     * pending/authorize/challenge are not final; anything else failed.
     */
    private function outcome(string $status, string $fraud): string
    {
        return match (true) {
            $status === 'settlement',
            $status === 'capture' && $fraud === 'accept' => PaymentNotification::SETTLED,
            in_array($status, ['pending', 'authorize', 'capture'], true) => PaymentNotification::PENDING,
            default => PaymentNotification::FAILED,
        };
    }

    private function baseUrl(): string
    {
        return $this->production
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }
}
