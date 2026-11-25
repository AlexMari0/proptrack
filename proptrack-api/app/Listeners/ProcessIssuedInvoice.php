<?php

namespace App\Listeners;

use App\Events\InvoiceIssued;
use App\Jobs\GenerateInvoicePdfJob;
use App\Notifications\InvoiceCreatedNotification;

class ProcessIssuedInvoice
{
    public function handle(InvoiceIssued $event): void
    {
        $invoice = $event->invoice;
        $invoice->loadMissing(['contract', 'tenant', 'property']);

        GenerateInvoicePdfJob::dispatch($invoice);

        $invoice->tenant->getNotifiable()->notify(new InvoiceCreatedNotification($invoice));
    }
}
