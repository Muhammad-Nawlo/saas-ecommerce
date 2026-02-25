<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_orders', 'snapshot_hash')) {
                $table->string('snapshot_hash', 64)->nullable()->after('snapshot')->comment('SHA-256 of immutable snapshot for tamper detection');
            }
        });
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'snapshot_hash')) {
                $table->string('snapshot_hash', 64)->nullable()->after('snapshot')->comment('SHA-256 of immutable snapshot for tamper detection');
            }
        });
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'snapshot_hash')) {
                $table->string('snapshot_hash', 64)->nullable()->after('payment_amount_base')->comment('SHA-256 of immutable payment fields for tamper detection');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_orders', function (Blueprint $table): void {
            $table->dropColumn('snapshot_hash');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('snapshot_hash');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('snapshot_hash');
        });
    }
};
