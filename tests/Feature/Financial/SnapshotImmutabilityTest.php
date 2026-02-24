<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialOrderItem;
use App\Models\Financial\TaxRate;
use App\Services\Financial\OrderLockService;
use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createTenantForSnapshot(): Tenant
{
    $tenant = \App\Landlord\Models\Tenant::create(['name' => 'Snapshot Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--force' => true,
        ]);
    });
    return $tenant;
}

test('snapshot is stored after lock and contains full order state', function (): void {
    $tenant = createTenantForSnapshot();
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FO-SNAP-001',
        'subtotal_cents' => 0,
        'tax_total_cents' => 0,
        'total_cents' => 0,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_DRAFT,
    ]);
    FinancialOrderItem::create([
        'order_id' => $order->id,
        'description' => 'Line item',
        'quantity' => 1,
        'unit_price_cents' => 5000,
        'subtotal_cents' => 5000,
        'tax_cents' => 0,
        'total_cents' => 5000,
        'metadata' => null,
    ]);
    TaxRate::create([
        'tenant_id' => $tenant->id,
        'name' => 'GST',
        'percentage' => 5.00,
        'country_code' => 'US',
        'region_code' => null,
        'is_active' => true,
    ]);

    $order->load('items');
    $lockService = app(OrderLockService::class);
    $lockService->lock($order, 'US', null);

    $order->refresh();
    expect($order->snapshot)->not->toBeNull();
    expect($order->snapshot)->toHaveKeys(['locked_at', 'currency', 'subtotal_cents', 'tax_total_cents', 'total_cents', 'tax_lines', 'items']);
    expect($order->snapshot['items'])->toHaveCount(1);
    expect($order->isLocked())->toBeTrue();
})->group('financial');

test('locked order cannot be locked again', function (): void {
    $tenant = createTenantForSnapshot();
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FO-SNAP-002',
        'subtotal_cents' => 1000,
        'tax_total_cents' => 0,
        'total_cents' => 1000,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_PENDING,
        'locked_at' => now(),
    ]);
    FinancialOrderItem::create([
        'order_id' => $order->id,
        'description' => 'Item',
        'quantity' => 1,
        'unit_price_cents' => 1000,
        'subtotal_cents' => 1000,
        'tax_cents' => 0,
        'total_cents' => 1000,
        'metadata' => null,
    ]);

    $lockService = app(OrderLockService::class);
    $lockService->lock($order, 'US', null);
})->throws(\InvalidArgumentException::class, 'already locked')->group('financial');
