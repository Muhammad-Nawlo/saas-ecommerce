<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('base_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('target_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', 18, 8);
            $table->enum('source', ['manual', 'api'])->default('manual');
            $table->dateTime('effective_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['base_currency_id', 'target_currency_id', 'effective_at'], 'exchange_rates_base_target_effective_unique');
            $table->index(['base_currency_id', 'target_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
