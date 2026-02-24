<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add base/display currency and rate snapshot for multi-currency. All nullable for backward compatibility. */
    public function up(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->string('base_currency', 3)->nullable()->after('currency');
            $table->string('display_currency', 3)->nullable()->after('base_currency');
            $table->json('exchange_rate_snapshot')->nullable()->after('display_currency');
            $table->bigInteger('subtotal_base_cents')->nullable()->after('exchange_rate_snapshot');
            $table->bigInteger('subtotal_display_cents')->nullable()->after('subtotal_base_cents');
            $table->bigInteger('tax_base_cents')->nullable()->after('tax_total_cents');
            $table->bigInteger('tax_display_cents')->nullable()->after('tax_base_cents');
            $table->bigInteger('total_base_cents')->nullable()->after('total_cents');
            $table->bigInteger('total_display_cents')->nullable()->after('total_base_cents');
        });
    }

    public function down(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'base_currency', 'display_currency', 'exchange_rate_snapshot',
                'subtotal_base_cents', 'subtotal_display_cents',
                'tax_base_cents', 'tax_display_cents',
                'total_base_cents', 'total_display_cents',
            ]);
        });
    }
};
