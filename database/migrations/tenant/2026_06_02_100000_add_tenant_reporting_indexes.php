<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for tenant-scoped reporting and listing (N+1 / high-volume readiness).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at'], 'financial_orders_tenant_created_idx');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at'], 'payments_tenant_created_idx');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at'], 'invoices_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->dropIndex('financial_orders_tenant_created_idx');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_tenant_created_idx');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_tenant_created_idx');
        });
    }
};
