<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Inventory\InventoryLocationStock;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Services\Inventory\InventoryAllocationService;
use App\Services\Inventory\InventoryLocationService;
use App\Services\Inventory\InventoryStockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Cancel Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
    $location = app(InventoryLocationService::class)->getOrCreateDefaultLocation($this->tenant->id);
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
    app(InventoryStockAdjustmentService::class)->adjust($this->productId, $location->id, 50, 'Setup');
    $this->order = OrderModel::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'customer_email' => 'c@d.com',
        'status' => 'pending',
        'total_amount' => 0,
        'currency' => 'USD',
    ]);
    $this->order->items()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'order_id' => $this->order->id,
        'product_id' => $this->productId,
        'quantity' => 15,
        'unit_price_amount' => 1000,
        'unit_price_currency' => 'USD',
        'total_price_amount' => 15000,
        'total_price_currency' => 'USD',
    ]);
    app(InventoryAllocationService::class)->allocateStock($this->order);
    $this->locationId = $location->id;
});

test('cancel order releases reservation', function (): void {
    app(InventoryAllocationService::class)->releaseReservation($this->order);
    $stock = InventoryLocationStock::where('product_id', $this->productId)->where('location_id', $this->locationId)->first();
    expect($stock->reserved_quantity)->toBe(0);
    expect($stock->quantity)->toBe(50);
})->group('multi_location_inventory');
