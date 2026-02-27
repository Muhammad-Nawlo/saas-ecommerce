<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->uuid('order_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('invoice_number')->unique();
            $table->enum('status', [
                'draft',
                'issued',
                'paid',
                'partially_paid',
                'void',
                'refunded',
            ])->default('draft')->index();
            $table->string('currency', 3);
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_total_cents')->default(0);
            $table->bigInteger('discount_total_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->date('due_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);

            $table->foreign('order_id')->references('id')->on('financial_orders')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
