<?php

namespace App\Payments;

use App\Models\Invoice;

/**
 * The seam between the Payments module and a payment provider.
 *
 * Adapters: MidtransGateway (production and sandbox) and SimulatedGateway
 * (local dev, only when MIDTRANS_SIMULATE is on outside production). The
 * binding is chosen once, in AppServiceProvider.
 */
interface PaymentGateway
{
    /** Stored on the Invoice as payment_gateway when it settles. */
    public function name(): string;

    /**
     * Open a checkout for the Invoice's full amount.
     *
     * @throws PaymentGatewayError when the provider refuses or is unreachable.
     */
    public function startCheckout(Invoice $invoice): Checkout;

    /**
     * Authenticate and interpret an incoming provider notification.
     *
     * Returns null when the notification is not authentic (bad signature).
     */
    public function parseNotification(array $payload): ?PaymentNotification;
}
