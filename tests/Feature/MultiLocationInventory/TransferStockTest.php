<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryLocationStock;
use App\Services\Inventory\InventoryLocationService;
use App\Services\Inventory\InventoryStockAdjustmentService;
use App\Services\Inventory\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Transfer Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
    $default = app(InventoryLocationService::class)->getOrCreateDefaultLocation($this->tenant->id);
    $this->productId = (string) \Illuminate\Support\Str::uuid();
    \Illuminate\Support\Facades\DB::table('products')->insert([
        'id' => $this->productId,
        'tenant_id' => $this->tenant->id,
        'name' => 'Product',
        'slug' => 'product',
        'description' => '',
        'price_minor_units' => 1000,
        'currency' => 'USD',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(InventoryStockAdjustmentService::class)->adjust($this->productId, $default->id, 50, 'Setup');
    $this->fromId = $default->id;
    $this->toLocation = InventoryLocation::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'name' => 'Store B',
        'code' => 'STORE_B',
        'type' => InventoryLocation::TYPE_RETAIL_STORE,
        'is_active' => true,
    ]);
});

test('transfer stock moves quantity when feature enabled', function (): void {
    if (!function_exists('tenant_feature') || !tenant_feature('multi_location_inventory')) {
        $this->markTestSkipped('Multi-location feature required');
    }
    $service = app(InventoryTransferService::class);
    $transfer = $service->transfer($this->productId, $this->fromId, $this->toLocation->id, 20);
    expect($transfer->status)->toBe('completed');
    $fromStock = InventoryLocationStock::where('product_id', $this->productId)->where('location_id', $this->fromId)->first();
    $toStock = InventoryLocationStock::where('product_id', $this->productId)->where('location_id', $this->toLocation->id)->first();
    expect($fromStock->quantity)->toBe(30);
    expect($toStock->quantity)->toBe(20);
})->group('multi_location_inventory');
