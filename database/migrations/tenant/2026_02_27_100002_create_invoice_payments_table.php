<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('financial_transaction_id')->nullable()->index();
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
