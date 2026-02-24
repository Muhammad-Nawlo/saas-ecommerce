<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialOrderItem;
use App\Models\Financial\FinancialTransaction;
use App\Models\Financial\TaxRate;
use App\Services\Financial\OrderLockService;
use App\Services\Financial\RefundService;
use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createTenantForRefund(): Tenant
{
    $tenant = \App\Landlord\Models\Tenant::create(['name' => 'Refund Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--force' => true,
        ]);
    });
    return $tenant;
}

test('refund over paid amount throws', function (): void {
    $tenant = createTenantForRefund();
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FO-REF-001',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'currency' => 'USD',
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
        'meta' => null,
    ]);

    $refundService = app(RefundService::class);
    $refundService->refund($order, 15000); // more than paid
})->throws(\InvalidArgumentException::class, 'cannot exceed paid amount')->group('financial');

test('refund within paid amount succeeds', function (): void {
    $tenant = createTenantForRefund();
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FO-REF-002',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
    ]);
    FinancialTransaction::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => FinancialTransaction::TYPE_CREDIT,
        'amount_cents' => 10000,
        'currency' => 'USD',
        'provider_reference' => 'pay_2',
        'status' => FinancialTransaction::STATUS_COMPLETED,
        'meta' => null,
    ]);

    $refundService = app(RefundService::class);
    $refundService->refund($order, 5000);

    $order->refresh();
    expect($order->status)->toBe('refunded');
    $refundTx = $order->transactions()->where('type', FinancialTransaction::TYPE_REFUND)->first();
    expect($refundTx)->not->toBeNull();
    expect($refundTx->amount_cents)->toBe(5000);
})->group('financial');
