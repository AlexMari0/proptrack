<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(private readonly Invoice $invoice)
    {
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $path = "invoices/invoice-{$this->invoice->id}.pdf";

        try {
            $invoiceService->generatePdf($this->invoice, $path);
            Log::info("Invoice PDF generated: {$this->invoice->invoice_number}");
        } catch (\Throwable $e) {
            Log::error("Failed to generate invoice PDF: {$this->invoice->invoice_number}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
