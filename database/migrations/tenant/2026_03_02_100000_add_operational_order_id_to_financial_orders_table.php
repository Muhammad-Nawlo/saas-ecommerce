<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links financial_orders to operational orders (orders table).
 * One-to-one: one financial order per operational order when synced from payment success.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->uuid('operational_order_id')->nullable()->after('id')->unique();
            $table->index('operational_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->dropIndex(['operational_order_id']);
            $table->dropColumn('operational_order_id');
        });
    }
};
