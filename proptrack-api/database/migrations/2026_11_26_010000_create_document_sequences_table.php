<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-prefix, per-year counters for document numbers (INV-2026-0001,
 * TKT-2026-0001). Seeded from the highest numbers already issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number');
            $table->primary(['prefix', 'year']);
        });

        foreach (['INV' => ['invoices', 'invoice_number'], 'TKT' => ['tickets', 'ticket_number']] as $prefix => [$table, $column]) {
            $max = [];
            foreach (DB::table($table)->pluck($column) as $number) {
                if (preg_match("/^{$prefix}-(\\d{4})-(\\d+)$/", $number, $m)) {
                    $max[$m[1]] = max($max[$m[1]] ?? 0, (int) $m[2]);
                }
            }
            foreach ($max as $year => $last) {
                DB::table('document_sequences')->insert(['prefix' => $prefix, 'year' => $year, 'last_number' => $last]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
