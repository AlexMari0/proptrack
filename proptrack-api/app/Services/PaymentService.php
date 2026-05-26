<?php

namespace App\Services;

use App\Models\Invoice;
use App\Jobs\HandleMidtransWebhookJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    // ─── Midtrans Configuration ───────────────────────────────────────────────

    private function midtransServerKey(): string
    {
        return config('services.midtrans.server_key', '');
    }

    private function midtransBaseUrl(): string
    {
        $production = config('services.midtrans.is_production', false);
        return $production
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    private function midtransAuthHeader(): string
    {
        // Midtrans auth: base64-encode "SERVER_KEY:"
        return 'Basic ' . base64_encode($this->midtransServerKey() . ':');
    }

    // ─── Create Transaction ───────────────────────────────────────────────────

    /**
     * Create a Midtrans Snap transaction for a given invoice.
     * Returns the token and redirect URL.
     *
     * @return array{transaction_token: string, redirect_url: string}
     * @throws ValidationException
     */
    public function createMidtransTransaction(Invoice $invoice): array
    {
        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'invoice_id' => ['This invoice has already been paid.'],
            ]);
        }

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice_id' => ['This invoice has been cancelled.'],
            ]);
        }

        $invoice->loadMissing(['tenant', 'property']);

        $payload = [
            'transaction_details' => [
                'order_id'     => $invoice->invoice_number,
                'gross_amount' => $invoice->amount,
            ],
            'customer_details' => [
                'first_name' => $invoice->tenant->name,
                'email'      => $invoice->tenant->email,
                'phone'      => $invoice->tenant->phone ?? '',
            ],
            'item_details' => [
                [
                    'id'       => $invoice->id,
                    'price'    => $invoice->amount,
                    'quantity' => 1,
                    'name'     => "Sewa - {$invoice->property->name} ({$invoice->billing_month})",
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->midtransAuthHeader(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post("{$this->midtransBaseUrl()}/transactions", $payload);

        if ($response->failed()) {
            // Graceful fallback for local development with placeholder/mock keys
            if (app()->environment('local') && (
                $this->midtransServerKey() === '' || 
                $this->midtransServerKey() === 'mock' || 
                env('MIDTRANS_MOCK', false) === true
            )) {
                Log::warning('Midtrans API failed in local environment with mock key. Gracefully falling back to a local mock transaction.');
                $token = 'mock-snap-token-' . \Illuminate\Support\Str::uuid();
                $redirectUrl = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $token;

                $invoice->update([
                    'payment_token'   => $token,
                    'payment_gateway' => 'midtrans',
                ]);

                return [
                    'transaction_token' => $token,
                    'redirect_url'      => $redirectUrl,
                ];
            }

            Log::error('Midtrans transaction creation failed', [
                'invoice_number' => $invoice->invoice_number,
                'status'         => $response->status(),
                'body'           => $response->json(),
            ]);
            throw ValidationException::withMessages([
                'gateway' => ['Payment gateway error. Please try again later.'],
            ]);
        }

        $data = $response->json();
        $token = $data['token'];
        $redirectUrl = $data['redirect_url'];

        // Persist the token on the invoice so status polling can verify it
        $invoice->update([
            'payment_token'   => $token,
            'payment_gateway' => 'midtrans',
        ]);

        return [
            'transaction_token' => $token,
            'redirect_url'      => $redirectUrl,
        ];
    }

    // ─── Webhook Handling ─────────────────────────────────────────────────────

    /**
     * Verify the Midtrans webhook notification signature.
     *
     * Midtrans computes: sha512(order_id + status_code + gross_amount + server_key)
     */
    public function verifyMidtransSignature(array $payload): bool
    {
        // Graceful mock bypass in local environment
        if (app()->environment('local') && ($payload['mock'] ?? false) === true) {
            return true;
        }

        $signatureKey = hash('sha512',
            ($payload['order_id']     ?? '') .
            ($payload['status_code']  ?? '') .
            ($payload['gross_amount'] ?? '') .
            $this->midtransServerKey()
        );

        return hash_equals($signatureKey, $payload['signature_key'] ?? '');
    }

    /**
     * Dispatch the webhook job. Called from controller after signature verification.
     */
    public function dispatchMidtransWebhook(array $payload): void
    {
        HandleMidtransWebhookJob::dispatch($payload);
    }

    /**
     * Core payment success handler — updates invoice to paid.
     * Called inside HandleMidtransWebhookJob.
     */
    public function handlePaymentSuccess(string $invoiceNumber, string $gateway): void
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        if (! $invoice || $invoice->isPaid()) {
            return;
        }

        $invoice->update([
            'status'          => 'paid',
            'paid_at'         => now(),
            'payment_gateway' => $gateway,
        ]);

        $invoice->loadMissing(['tenant', 'property']);

        // Notify tenant
        $invoice->tenant->getNotifiable()->notify(new \App\Notifications\PaymentConfirmedNotification($invoice));

        // Broadcast PaymentConfirmed event
        event(new \App\Events\PaymentConfirmed($invoice));

        Log::info("Invoice paid: {$invoiceNumber} via {$gateway}");
    }

    /**
     * Get the current payment status of an invoice.
     */
    public function getInvoiceStatus(Invoice $invoice): array
    {
        return [
            'invoice_id'    => $invoice->id,
            'status'        => $invoice->status,
            'paid_at'       => $invoice->paid_at?->toIso8601String(),
            'payment_gateway' => $invoice->payment_gateway,
        ];
    }
}
