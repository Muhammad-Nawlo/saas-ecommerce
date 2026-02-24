<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of tax applied to an order at lock time. Do not use live tax_rates for locked orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_order_tax_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('tax_rate_name');
            $table->decimal('tax_percentage', 5, 2);
            $table->bigInteger('taxable_amount_cents');
            $table->bigInteger('tax_amount_cents');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('id')->on('financial_orders')->cascadeOnDelete();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_order_tax_lines');
    }
};
