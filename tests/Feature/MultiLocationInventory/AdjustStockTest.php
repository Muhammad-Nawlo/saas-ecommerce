<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryLocationStock;
use App\Services\Inventory\InventoryLocationService;
use App\Services\Inventory\InventoryStockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Adjust Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
    $this->location = app(InventoryLocationService::class)->getOrCreateDefaultLocation($this->tenant->id);
    $this->productId = (string) \Illuminate\Support\Str::uuid();
    \Illuminate\Support\Facades\DB::table('products')->insert([
        'id' => $this->productId,
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'description' => '',
        'price_minor_units' => 1000,
        'currency' => 'USD',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('adjust stock increases quantity', function (): void {
    $service = app(InventoryStockAdjustmentService::class);
    $stock = $service->adjust($this->productId, $this->location->id, 10, 'Initial receipt');
    expect($stock->quantity)->toBe(10);
    $stock = $service->adjust($this->productId, $this->location->id, 5, 'Restock');
    expect($stock->quantity)->toBe(15);
})->group('multi_location_inventory');
