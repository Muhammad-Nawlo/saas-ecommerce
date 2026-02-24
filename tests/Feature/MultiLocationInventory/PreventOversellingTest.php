<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Services\Inventory\InventoryAllocationService;
use App\Services\Inventory\InventoryLocationService;
use App\Services\Inventory\InventoryStockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Oversell Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
    $this->location = app(InventoryLocationService::class)->getOrCreateDefaultLocation($this->tenant->id);
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
    app(InventoryStockAdjustmentService::class)->adjust($this->productId, $this->location->id, 5, 'Setup');
    $this->order = OrderModel::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'customer_email' => 'x@y.com',
        'status' => 'pending',
        'total_amount' => 0,
        'currency' => 'USD',
    ]);
    $this->order->items()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'order_id' => $this->order->id,
        'product_id' => $this->productId,
        'quantity' => 10,
        'unit_price_amount' => 1000,
        'unit_price_currency' => 'USD',
        'total_price_amount' => 10000,
        'total_price_currency' => 'USD',
    ]);
});

test('allocate stock prevents overselling', function (): void {
    app(InventoryAllocationService::class)->allocateStock($this->order);
})->throws(\InvalidArgumentException::class)->group('multi_location_inventory');
