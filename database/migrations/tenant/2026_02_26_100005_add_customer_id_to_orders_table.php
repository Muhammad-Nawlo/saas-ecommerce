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
        DB::statement('DROP VIEW IF EXISTS customer_summaries');
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->after('tenant_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
        DB::statement(self::VIEW_SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS customer_summaries');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
        DB::statement(self::VIEW_SQL);
    }
};
