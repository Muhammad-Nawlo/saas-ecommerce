<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pivot: which currencies are enabled for selling per tenant (when allow_multi_currency = true). */
    public function up(): void
    {
        Schema::create('tenant_enabled_currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_enabled_currencies');
    }
};
