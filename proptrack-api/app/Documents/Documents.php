<?php

namespace App\Documents;

use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * The PDFs PropTrack hands out, rendered on demand and cached on the local disk.
 *
 * A cached file is reused only while the record it shows is unchanged: each
 * cache name carries a version (the record's last change, and for invoices
 * the effective status, since an invoice becomes overdue without being
 * edited). Older versions are deleted when a new one is rendered.
 */
class Documents
{
    /** Absolute path to the invoice PDF. */
    public function invoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['contract', 'tenant', 'property']);

        return $this->pdf(
            "invoices/invoice-{$invoice->id}",
            $invoice->effectiveStatus() . '-' . $invoice->updated_at?->getTimestamp(),
            fn () => Pdf::view('pdf.invoice', ['invoice' => $invoice]),
        );
    }

    /** Absolute path to the contract PDF. */
    public function contract(Contract $contract): string
    {
        $contract->loadMissing(['tenant', 'property']);

        return $this->pdf(
            "contracts/contract-{$contract->id}",
            (string) $contract->updated_at?->getTimestamp(),
            fn () => Pdf::view('pdf.contract', ['contract' => $contract])->landscape(false),
        );
    }

    /**
     * Absolute path to a financial report PDF.
     *
     * @param  array<string, mixed>  $summary  ReportService financial summary
     */
    public function financialReport(array $summary): string
    {
        return $this->pdf(
            'reports/report-' . md5(json_encode($summary)),
            'v1',
            fn () => Pdf::view('pdf.financial-report', ['data' => $summary]),
        );
    }

    /**
     * @param  callable(): PdfBuilder  $render
     */
    private function pdf(string $name, string $version, callable $render): string
    {
        $disk = Storage::disk('local');
        $path = "{$name}-{$version}.pdf";

        if (! $disk->exists($path)) {
            foreach ($disk->files(dirname($path)) as $file) {
                if (str_starts_with($file, "{$name}-")) {
                    $disk->delete($file);
                }
            }

            $disk->makeDirectory(dirname($path));
            $render()->save($disk->path($path));
        }

        return $disk->path($path);
    }
}
