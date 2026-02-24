<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry ledger foundation. Immutable entries; debit/credit always balanced.
 * Source reference: FinancialOrder (order_id points to financial_orders.id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->index();
            $table->string('name');
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ledger_id');
            $table->string('code', 32)->index();
            $table->string('name');
            $table->string('type', 32); // revenue, tax_payable, platform_commission, accounts_receivable, cash, refund_liability
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['ledger_id', 'code']);
            $table->foreign('ledger_id')->references('id')->on('ledgers')->cascadeOnDelete();
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ledger_id');
            $table->string('reference_type', 64)->nullable(); // e.g. financial_order, refund
            $table->string('reference_id', 36)->nullable()->index();
            $table->string('description')->nullable();
            $table->timestamp('transaction_at')->useCurrent();
            $table->timestamps();
            $table->foreign('ledger_id')->references('id')->on('ledgers')->cascadeOnDelete();
            $table->index(['ledger_id', 'transaction_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ledger_transaction_id');
            $table->uuid('ledger_account_id');
            $table->string('type', 16); // debit, credit
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->cascadeOnDelete();
            $table->foreign('ledger_account_id')->references('id')->on('ledger_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('ledgers');
    }
};
