<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->uuid('order_id')->nullable()->index();
            $table->enum('type', ['debit', 'credit', 'refund']);
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('provider_reference')->nullable()->index();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->index();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('id')->on('financial_orders')->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
