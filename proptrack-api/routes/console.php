<?php

use App\Console\Commands\GenerateMonthlyInvoicesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate invoices for the current billing month. Runs daily so a contract
// activated mid-month is still billed; issuing is idempotent per month.
// Overdue is derived from due_date, not set by a job (docs/adr/0001-overdue-is-derived.md).
Schedule::command(GenerateMonthlyInvoicesCommand::class)->daily();

// Dispatch daily scheduled notifications (due invoices, expiring contracts)
Schedule::command(\App\Console\Commands\SendScheduledNotificationsCommand::class)->daily();
