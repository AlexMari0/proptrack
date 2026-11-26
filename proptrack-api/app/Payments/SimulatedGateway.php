<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Support\Str;

/**
 * Local payment simulator. Bound only when MIDTRANS_SIMULATE is on and the
 * app is not in production (AppServiceProvider). Never calls a provider;
 * notifications from the web app's simulator carry `mock: true` and no signature.
 */
class SimulatedGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'simulated';
    }

    public function startCheckout(Invoice $invoice): Checkout
    {
        $token = 'sim-' . Str::uuid();

        return new Checkout($token, url("/simulated-checkout/{$token}"), simulated: true);
    }

    public function parseNotification(array $payload): ?PaymentNotification
    {
        if (($payload['mock'] ?? false) !== true || ! isset($payload['order_id'])) {
            return null;
        }

        return new PaymentNotification(
            invoiceNumber: (string) $payload['order_id'],
            outcome: ($payload['transaction_status'] ?? '') === 'settlement'
                ? PaymentNotification::SETTLED
                : PaymentNotification::FAILED,
            amount: (int) round((float) ($payload['gross_amount'] ?? 0)),
            gateway: $this->name(),
        );
    }
}
