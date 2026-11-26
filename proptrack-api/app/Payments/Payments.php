<?php

namespace App\Payments;

use App\Audit\AuditContext;
use App\Exceptions\InvalidInvoiceTransition;
use App\Jobs\SettlePaymentJob;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Paying an Invoice: open a checkout, then settle it when the provider
 * confirms. The provider sits behind PaymentGateway; the Invoice's own
 * status change is Invoice::markPaid().
 */
class Payments
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * @throws ValidationException when the Invoice cannot be paid or the provider fails.
     */
    public function startCheckout(Invoice $invoice): Checkout
    {
        if (! $invoice->isPayable()) {
            throw ValidationException::withMessages([
                'invoice_id' => [$invoice->isPaid()
                    ? 'This invoice has already been paid.'
                    : 'This invoice cannot be paid.'],
            ]);
        }

        try {
            $checkout = $this->gateway->startCheckout($invoice);
        } catch (PaymentGatewayError) {
            throw ValidationException::withMessages([
                'gateway' => ['Payment gateway error. Please try again later.'],
            ]);
        }

        $invoice->update(['payment_token' => $checkout->token]);

        return $checkout;
    }

    /**
     * Accept a provider notification: authenticate it now, settle it on the queue.
     *
     * Returns false when the notification is not authentic.
     */
    public function receiveNotification(array $payload): bool
    {
        $notification = $this->gateway->parseNotification($payload);

        if ($notification === null) {
            return false;
        }

        SettlePaymentJob::dispatch($notification->toArray());

        return true;
    }

    /**
     * Apply an authenticated notification. Safe to repeat: providers retry.
     */
    public function settle(PaymentNotification $notification): void
    {
        if (! $notification->isSettled()) {
            Log::info("Payment notification ignored: {$notification->invoiceNumber} is {$notification->outcome}");
            return;
        }

        $invoice = Invoice::where('invoice_number', $notification->invoiceNumber)->first();

        if (! $invoice) {
            Log::warning("Payment settled for unknown invoice {$notification->invoiceNumber}");
            return;
        }

        if ($notification->amount !== $invoice->amount) {
            Log::warning("Payment amount mismatch for {$invoice->invoice_number}: got {$notification->amount}, expected {$invoice->amount}");
            return;
        }

        try {
            // Notification and PaymentConfirmed broadcast happen in the InvoicePaid listener.
            $paid = app(AuditContext::class)->actingAs(
                "gateway:{$notification->gateway}",
                fn () => $invoice->markPaid($notification->gateway),
            );

            if ($paid) {
                Log::info("Invoice paid: {$invoice->invoice_number} via {$notification->gateway}");
            }
        } catch (InvalidInvoiceTransition $e) {
            Log::warning("Payment settled for unpayable invoice: {$e->getMessage()}");
        }
    }
}
