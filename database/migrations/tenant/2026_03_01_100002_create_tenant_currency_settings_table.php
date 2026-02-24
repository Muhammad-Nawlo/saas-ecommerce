<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_currency_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 36)->unique();
            $table->foreignId('base_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->boolean('allow_multi_currency')->default(false);
            $table->enum('rounding_strategy', ['bankers', 'half_up', 'half_down'])->default('half_up');
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_currency_settings');
    }
};
