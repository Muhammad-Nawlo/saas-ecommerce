<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialOrderItem;
use App\Models\Financial\TaxRate;
use App\Services\Financial\OrderLockService;
use App\Services\Financial\TaxCalculator;
use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createFinancialTenant(): Tenant
{
    $tenant = \App\Landlord\Models\Tenant::create(['name' => 'Financial Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--force' => true,
        ]);
    });
    return $tenant;
}

test('order calculation: total equals subtotal plus tax after lock', function (): void {
    $tenant = createFinancialTenant();
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FO-TEST-001',
        'subtotal_cents' => 0,
        'tax_total_cents' => 0,
        'total_cents' => 0,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_DRAFT,
    ]);
    FinancialOrderItem::create([
        'order_id' => $order->id,
        'description' => 'Item A',
        'quantity' => 2,
        'unit_price_cents' => 1000,
        'subtotal_cents' => 2000,
        'tax_cents' => 0,
        'total_cents' => 2000,
        'metadata' => null,
    ]);
    TaxRate::create([
        'tenant_id' => $tenant->id,
        'name' => 'VAT',
        'percentage' => 10.00,
        'country_code' => 'US',
        'region_code' => null,
        'is_active' => true,
    ]);

    $order->load('items');
    $lockService = app(OrderLockService::class);
    $lockService->lock($order, 'US', null);

    $order->refresh();
    expect($order->subtotal_cents)->toBe(2000);
    expect($order->subtotal_cents + $order->tax_total_cents)->toBe($order->total_cents);
    expect($order->locked_at)->not->toBeNull();
    expect($order->snapshot)->toBeArray();
    expect($order->snapshot['total_cents'])->toBe($order->total_cents);
})->group('financial');
