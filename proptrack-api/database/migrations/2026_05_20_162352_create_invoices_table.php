<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('property_id')->constrained()->cascadeOnDelete();

            // Human-readable invoice number: INV-YYYY-NNNN
            $table->string('invoice_number', 20)->unique();

            $table->enum('status', ['unpaid', 'paid', 'overdue', 'cancelled'])->default('unpaid');

            $table->unsignedBigInteger('amount');         // IDR, copied from contract.monthly_rent
            $table->string('billing_month', 7);           // e.g. "2025-02"
            $table->date('due_date');                     // contract.billing_date of that month

            $table->timestamp('paid_at')->nullable();
            $table->string('payment_gateway')->nullable(); // midtrans / xendit (Phase 3.2)
            $table->string('payment_token')->nullable();   // gateway transaction token (Phase 3.2)

            $table->timestamps();

            // One invoice per contract per billing month
            $table->unique(['contract_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
