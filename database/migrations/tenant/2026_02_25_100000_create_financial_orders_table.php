<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial orders (snapshot-based, immutable when locked).
 * tenant_id nullable for landlord-level orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->string('property_id', 36)->nullable()->index();
            $table->string('order_number')->unique();
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_total_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->string('currency', 3);
            $table->enum('status', ['draft', 'pending', 'paid', 'failed', 'refunded'])->default('draft')->index();
            $table->json('snapshot')->nullable()->comment('Full immutable order snapshot after lock');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_orders');
    }
};
