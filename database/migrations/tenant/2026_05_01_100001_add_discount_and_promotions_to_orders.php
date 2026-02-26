<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VIEW_SQL = "CREATE VIEW customer_summaries AS SELECT tenant_id, customer_email AS email, COUNT(*) AS order_count, COALESCE(SUM(total_amount),0) AS total_spent FROM orders GROUP BY tenant_id, customer_email";

    public function up(): void
    {
        if (Schema::hasTable('financial_orders') && !Schema::hasColumn('financial_orders', 'discount_total_cents')) {
            Schema::table('financial_orders', function (Blueprint $table): void {
                $table->bigInteger('discount_total_cents')->default(0)->after('tax_total_cents');
            });
        }
        DB::statement('DROP VIEW IF EXISTS customer_summaries');
        Schema::table('orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('orders', 'discount_total_cents')) {
                $table->bigInteger('discount_total_cents')->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('orders', 'applied_promotions')) {
                $table->json('applied_promotions')->nullable()->after('discount_total_cents');
            }
        });
        DB::statement(self::VIEW_SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS customer_summaries');
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->dropColumn('discount_total_cents');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_total_cents', 'applied_promotions']);
        });
        DB::statement(self::VIEW_SQL);
    }
};
