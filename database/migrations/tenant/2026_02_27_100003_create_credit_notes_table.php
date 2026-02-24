<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('reason');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamp('issued_at');
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
