<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Contract;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Generate invoices for all active contracts for a given billing month.
     * Skips contracts that already have an invoice for that month.
     *
     * @param  string $billingMonth  Format: "YYYY-MM"
     * @return int    Number of invoices created
     */
    public function generateMonthlyInvoices(string $billingMonth): int
    {
        $created = 0;

        Contract::with(['tenant', 'property'])
            ->where('status', 'active')
            ->chunk(100, function ($contracts) use ($billingMonth, &$created) {
                foreach ($contracts as $contract) {
                    // Skip if already generated for this month
                    $exists = Invoice::where('contract_id', $contract->id)
                        ->where('billing_month', $billingMonth)
                        ->exists();

                    if ($exists) continue;

                    $invoice = $this->createInvoiceForContract($contract, $billingMonth);
                    GenerateInvoicePdfJob::dispatch($invoice);

                    // Notify tenant
                    $tenantUser = \App\Models\User::where('email', $contract->tenant->email)->first();
                    if ($tenantUser) {
                        $tenantUser->notify(new \App\Notifications\InvoiceCreatedNotification($invoice));
                    }

                    $created++;
                }
            });

        return $created;
    }

    /**
     * Create a single invoice for a contract and billing month.
     */
    public function createInvoiceForContract(Contract $contract, string $billingMonth): Invoice
    {
        $dueDate = $this->calculateDueDate($billingMonth, $contract->billing_date);

        return DB::transaction(function () use ($contract, $billingMonth, $dueDate) {
            $invoiceNumber = $this->generateInvoiceNumber();

            return Invoice::create([
                'contract_id'    => $contract->id,
                'tenant_id'      => $contract->tenant_id,
                'property_id'    => $contract->property_id,
                'invoice_number' => $invoiceNumber,
                'status'         => 'unpaid',
                'amount'         => $contract->monthly_rent,
                'billing_month'  => $billingMonth,
                'due_date'       => $dueDate,
            ]);
        });
    }

    /**
     * Mark overdue: set status=overdue for all unpaid invoices past their due_date.
     *
     * @return int Number of invoices updated
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::where('status', 'unpaid')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
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

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Generate a unique invoice number in the format INV-YYYY-NNNN.
     */
    private function generateInvoiceNumber(): string
    {
        $year = now()->year;

        // Count invoices for this year (including failed transactions, use DB lock)
        $count = Invoice::whereYear('created_at', $year)->lockForUpdate()->count();
        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        return "INV-{$year}-{$sequence}";
    }

    /**
     * Calculate the due date: billing_date day of the billing month.
     * If billing_date doesn't exist in that month, use last day.
     */
    private function calculateDueDate(string $billingMonth, int $billingDate): Carbon
    {
        $date = Carbon::createFromFormat('Y-m', $billingMonth)->startOfMonth();
        $maxDay = $date->daysInMonth;

        return $date->setDay(min($billingDate, $maxDay));
    }
}
