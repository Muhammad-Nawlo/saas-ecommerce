<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialTransaction;
use App\Models\Invoice\Invoice;
use App\Models\Ledger\LedgerEntry;
use App\Models\Ledger\LedgerTransaction;
use App\Modules\Financial\Application\Services\FinancialReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('reconciliation returns no issues when ledger and order are consistent', function (): void {
    $tenant = Tenant::create(['name' => 'Reconcile', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FIN-REC-001',
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
    ]);
    FinancialTransaction::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => FinancialTransaction::TYPE_CREDIT,
        'amount_cents' => 10000,
        'currency' => 'USD',
        'provider_reference' => 'pay_1',
        'status' => FinancialTransaction::STATUS_COMPLETED,
    ]);
    Invoice::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'invoice_number' => 'INV-REC-001',
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'total_cents' => 10000,
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'discount_total_cents' => 0,
    ]);

    $service = app(FinancialReconciliationService::class);
    $issues = $service->reconcile();

    expect($issues)->toBeArray();
    expect($issues)->toHaveCount(0);

    tenancy()->end();
})->group('financial', 'reconciliation');

test('reconciliation detects unbalanced ledger', function (): void {
    $tenant = Tenant::create(['name' => 'Reconcile Unbal', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $ledger = \App\Models\Ledger\Ledger::create([
        'tenant_id' => $tenant->id,
        'name' => 'Default',
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $cash = \App\Models\Ledger\LedgerAccount::create([
        'ledger_id' => $ledger->id,
        'code' => 'CASH',
        'name' => 'Cash',
        'type' => \App\Models\Ledger\LedgerAccount::TYPE_CASH,
        'is_active' => true,
    ]);
    $rev = \App\Models\Ledger\LedgerAccount::create([
        'ledger_id' => $ledger->id,
        'code' => 'REV',
        'name' => 'Revenue',
        'type' => \App\Models\Ledger\LedgerAccount::TYPE_REVENUE,
        'is_active' => true,
    ]);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FIN-UB',
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
    ]);
    $tx = LedgerTransaction::create([
        'ledger_id' => $ledger->id,
        'reference_type' => 'financial_order',
        'reference_id' => $order->id,
        'description' => 'Test',
        'transaction_at' => now(),
    ]);
    LedgerEntry::create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $cash->id, 'type' => LedgerEntry::TYPE_DEBIT, 'amount_cents' => 10000, 'currency' => 'USD']);
    LedgerEntry::create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $rev->id, 'type' => LedgerEntry::TYPE_CREDIT, 'amount_cents' => 9999, 'currency' => 'USD']);

    $service = app(FinancialReconciliationService::class);
    $issues = $service->reconcile();

    expect($issues)->toHaveCount(1);
    expect($issues[0]['mismatch_type'])->toBe('ledger_unbalanced');

    tenancy()->end();
})->group('financial', 'reconciliation');
