<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production performance indexes. Add only where queries filter by these columns.
 * financial_orders: operational_order_id already unique+index. tenant_id+status exists.
 * invoices: order_id indexed; add (order_id, status) if filtering by both.
 * payments: add (order_id, status) for "payments for order by status".
 * inventory_movements: (product_id, created_at) and (location_id, created_at) exist.
 * tenant_audit_logs: created_at indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['order_id', 'status']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'status']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'status']);
        });
    }
};
