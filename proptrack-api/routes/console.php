<?php

use App\Console\Commands\GenerateMonthlyInvoicesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate invoices for the current month every day at midnight.
// Running daily (not monthly) so overdue detection also fires every day.
Schedule::command(GenerateMonthlyInvoicesCommand::class)->daily();

// Dispatch daily scheduled notifications (due invoices, expiring contracts)
Schedule::command(\App\Console\Commands\SendScheduledNotificationsCommand::class)->daily();
