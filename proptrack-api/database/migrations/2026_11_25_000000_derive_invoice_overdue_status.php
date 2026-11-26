<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Overdue is derived from due_date, no longer stored (docs/adr/0001-overdue-is-derived.md).
 * Stored 'overdue' rows were unpaid invoices past due, so they become 'unpaid'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')->where('status', 'overdue')->update(['status' => 'unpaid']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid', 'cancelled'])->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid', 'overdue', 'cancelled'])->default('unpaid')->change();
        });
    }
};
