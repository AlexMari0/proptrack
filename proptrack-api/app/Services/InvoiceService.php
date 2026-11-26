<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;

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
}
