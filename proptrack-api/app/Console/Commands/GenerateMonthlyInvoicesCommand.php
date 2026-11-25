<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use App\Models\Invoice;
use Illuminate\Console\Command;

class GenerateMonthlyInvoicesCommand extends Command
{
    /**
     * Run with the current month: php artisan invoices:generate
     * Override month:              php artisan invoices:generate --month=2025-02
     */
    protected $signature = 'invoices:generate {--month= : Billing month in YYYY-MM format (defaults to current month)}';

    protected $description = 'Generate invoices for all active contracts for a given billing month';

    public function __construct(private readonly InvoiceService $invoiceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $month = $this->option('month') ?? Invoice::billingToday()->format('Y-m');

        // Basic format validation
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Invalid month format: \"{$month}\". Expected YYYY-MM.");
            return self::FAILURE;
        }

        $this->info("Generating invoices for billing month: {$month}");

        $created = $this->invoiceService->generateMonthlyInvoices($month);

        $this->info("Done. {$created} invoice(s) created.");

        return self::SUCCESS;
    }
}
