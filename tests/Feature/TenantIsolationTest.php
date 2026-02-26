<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Support\TenantTestHelper;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

test('financial orders are isolated per tenant', function (): void {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'data' => []]);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'data' => []]);

    $tenantA->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    $tenantB->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });

    tenancy()->initialize($tenantA);
    $orderA = FinancialOrder::create([
        'tenant_id' => $tenantA->id,
        'order_number' => 'FIN-A-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 1000,
        'tax_total_cents' => 0,
        'total_cents' => 1000,
        'status' => FinancialOrder::STATUS_DRAFT,
        'snapshot' => null,
    ]);

    tenancy()->initialize($tenantB);
    $orderB = FinancialOrder::create([
        'tenant_id' => $tenantB->id,
        'order_number' => 'FIN-B-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 2000,
        'tax_total_cents' => 0,
        'total_cents' => 2000,
        'status' => FinancialOrder::STATUS_DRAFT,
        'snapshot' => null,
    ]);

    tenancy()->initialize($tenantA);
    $foundA = FinancialOrder::where('id', $orderA->id)->first();
    $foundB = FinancialOrder::where('id', $orderB->id)->first();

    expect($foundA)->not->toBeNull();
    expect($foundA->total_cents)->toBe(1000);
    expect($foundB)->toBeNull();

    tenancy()->initialize($tenantB);
    $foundBInB = FinancialOrder::where('id', $orderB->id)->first();
    $foundAInB = FinancialOrder::where('id', $orderA->id)->first();

    expect($foundBInB)->not->toBeNull();
    expect($foundBInB->total_cents)->toBe(2000);
    expect($foundAInB)->toBeNull();
})->group('tenant_isolation');

test('two tenants in same test do not leak data', function (): void {
    $tenant1 = TenantTestHelper::createAndMigrateTenant(['name' => 'Isolation Tenant 1']);
    $tenant2 = TenantTestHelper::createAndMigrateTenant(['name' => 'Isolation Tenant 2']);

    TenantTestHelper::initializeTenant($tenant1);
    ProductModel::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant1->id,
        'name' => 'Product T1',
        'slug' => 'product-t1',
        'description' => '',
        'price_minor_units' => 100,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    TenantTestHelper::initializeTenant($tenant2);
    $countT2 = ProductModel::count();
    $productT2 = ProductModel::where('slug', 'product-t1')->first();

    expect($countT2)->toBe(0);
    expect($productT2)->toBeNull();
})->group('tenant_isolation');
