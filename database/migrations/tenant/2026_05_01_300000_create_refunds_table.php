<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->string('financial_order_id', 36)->index();
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('reason')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('payment_reference')->nullable()->index();
            $table->string('financial_transaction_id', 36)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
