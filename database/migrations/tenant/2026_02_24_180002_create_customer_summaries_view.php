<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS customer_summaries');
        DB::statement("CREATE VIEW customer_summaries AS SELECT tenant_id, customer_email AS email, COUNT(*) AS order_count, COALESCE(SUM(total_amount),0) AS total_spent FROM orders GROUP BY tenant_id, customer_email");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS customer_summaries');
    }
};
