<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Events\PaymentConfirmed;
use App\Notifications\PaymentConfirmedNotification;

class AnnounceInvoicePaid
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;
        $invoice->loadMissing(['contract', 'tenant', 'property']);

        $invoice->tenant->getNotifiable()->notify(new PaymentConfirmedNotification($invoice));

        event(new PaymentConfirmed($invoice));
    }
}
