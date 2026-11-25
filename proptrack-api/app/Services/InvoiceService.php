<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate invoices for all active contracts for a given billing month.
     * Contracts that already have an invoice for that month are skipped (see Invoice::issueFor).
     *
     * @param  string $billingMonth  Format: "YYYY-MM"
     * @return int    Number of invoices created
     */
    public function generateMonthlyInvoices(string $billingMonth): int
    {
        $created = 0;

        Contract::where('status', 'active')
            ->chunk(100, function ($contracts) use ($billingMonth, &$created) {
                foreach ($contracts as $contract) {
                    if (Invoice::issueFor($contract, $billingMonth)) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    /**
     * Generate or retrieve the PDF path for a given invoice.
     */
    public function getOrGeneratePdf(Invoice $invoice): string
    {
        $path = "invoices/invoice-{$invoice->id}.pdf";

        if (! Storage::disk('local')->exists($path)) {
            $this->generatePdf($invoice, $path);
        }

        return Storage::disk('local')->path($path);
    }

    /**
     * Internal: generate the PDF and write it to local storage.
     */
    public function generatePdf(Invoice $invoice, string $storagePath): void
    {
        $invoice->loadMissing(['contract', 'tenant', 'property']);

        $pdf = \Spatie\LaravelPdf\Facades\Pdf::view('pdf.invoice', [
            'invoice' => $invoice,
        ]);

        $absolutePath = Storage::disk('local')->path($storagePath);
        $dir = dirname($absolutePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->save($absolutePath);
    }
}
