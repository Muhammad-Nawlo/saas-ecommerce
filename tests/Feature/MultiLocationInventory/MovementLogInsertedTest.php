<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Inventory\InventoryMovement;
use App\Services\Inventory\InventoryLocationService;
use App\Services\Inventory\InventoryStockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Movement Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
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
    $this->locationId = $location->id;
});

test('adjustment inserts movement record', function (): void {
    app(InventoryStockAdjustmentService::class)->adjust($this->productId, $this->locationId, 25, 'Test reason');
    $movement = InventoryMovement::where('product_id', $this->productId)->where('location_id', $this->locationId)->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe(InventoryMovement::TYPE_INCREASE);
    expect($movement->quantity)->toBe(25);
    expect($movement->meta)->toHaveKey('reason');
})->group('multi_location_inventory');
