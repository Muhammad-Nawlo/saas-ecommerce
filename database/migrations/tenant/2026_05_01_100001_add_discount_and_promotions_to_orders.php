<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_orders') && !Schema::hasColumn('financial_orders', 'discount_total_cents')) {
            Schema::table('financial_orders', function (Blueprint $table): void {
                $table->bigInteger('discount_total_cents')->default(0)->after('tax_total_cents');
            });
        }
        Schema::table('orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('orders', 'discount_total_cents')) {
                $table->bigInteger('discount_total_cents')->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('orders', 'applied_promotions')) {
                $table->json('applied_promotions')->nullable()->after('discount_total_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->dropColumn('discount_total_cents');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_total_cents', 'applied_promotions']);
        });
    }
};
