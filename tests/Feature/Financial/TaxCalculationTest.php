<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialOrderItem;
use App\Models\Financial\TaxRate;
use App\Services\Financial\TaxCalculator;
use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createFinancialTenantForTax(): Tenant
{
    $tenant = \App\Landlord\Models\Tenant::create(['name' => 'Tax Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--force' => true,
        ]);
    });
    return $tenant;
}

test('tax calculator returns correct subtotal tax and total', function (): void {
    $tenant = createFinancialTenantForTax();
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FO-TAX-001',
        'subtotal_cents' => 0,
        'tax_total_cents' => 0,
        'total_cents' => 0,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_DRAFT,
    ]);
    FinancialOrderItem::create([
        'order_id' => $order->id,
        'description' => 'Product',
        'quantity' => 1,
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'tax_cents' => 0,
        'total_cents' => 10000,
        'metadata' => null,
    ]);
    $rate = TaxRate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Sales Tax',
        'percentage' => 8.25,
        'country_code' => 'US',
        'region_code' => null,
        'is_active' => true,
    ]);

    $order->load('items');
    $calculator = app(TaxCalculator::class);
    $result = $calculator->calculate($order, [$rate]);

    expect($result->subtotal_cents)->toBe(10000);
    expect($result->tax_total_cents)->toBe(825); // 10000 * 8.25 / 100
    expect($result->total_cents)->toBe(10825);
    expect($result->currency)->toBe('USD');
    expect($result->taxLines)->toHaveCount(1);
    expect($result->taxLines[0]['tax_amount_cents'])->toBe(825);
})->group('financial');
