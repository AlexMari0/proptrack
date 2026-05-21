<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ContractExpiringNotification;
use App\Notifications\InvoiceDueNotification;
use Illuminate\Console\Command;

class SendScheduledNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch daily scheduled notifications for upcoming due invoices and expiring contracts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled notifications dispatch...');

        // 1. Invoices due in exactly 3 days
        $dueDateTarget = today()->addDays(3)->toDateString();
        $this->info("Checking for unpaid invoices due on: {$dueDateTarget}");

        $invoices = Invoice::with(['tenant', 'property'])
            ->where('status', 'unpaid')
            ->whereDate('due_date', $dueDateTarget)
            ->get();

        $invoiceCount = 0;
        foreach ($invoices as $invoice) {
            $tenantUser = User::where('email', $invoice->tenant->email)->first();
            if ($tenantUser) {
                $tenantUser->notify(new InvoiceDueNotification($invoice));
                $invoiceCount++;
            }
        }
        $this->info("Dispatched {$invoiceCount} due invoice notification(s).");

        // 2. Contracts expiring in exactly 30 days
        $expiringDateTarget = today()->addDays(30)->toDateString();
        $this->info("Checking for active contracts expiring on: {$expiringDateTarget}");

        $contracts = Contract::with(['tenant', 'property.owner'])
            ->where('status', 'active')
            ->whereDate('end_date', $expiringDateTarget)
            ->get();

        $contractCount = 0;
        foreach ($contracts as $contract) {
            // Notify tenant
            $tenantUser = User::where('email', $contract->tenant->email)->first();
            if ($tenantUser) {
                $tenantUser->notify(new ContractExpiringNotification($contract, 'tenant'));
            }

            // Notify owner
            $ownerUser = $contract->property->owner;
            if ($ownerUser) {
                $ownerUser->notify(new ContractExpiringNotification($contract, 'owner'));
            }

            $contractCount++;
        }
        $this->info("Dispatched {$contractCount} expiring contract notification(s).");

        $this->info('Scheduled notifications dispatch completed successfully.');
        return self::SUCCESS;
    }
}
