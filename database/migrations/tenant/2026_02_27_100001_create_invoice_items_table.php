<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
