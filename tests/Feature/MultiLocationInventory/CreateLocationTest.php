<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Inventory\InventoryLocation;
use App\Services\Inventory\InventoryLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'ML Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('create location', function (): void {
    $service = app(InventoryLocationService::class);
    $location = $service->create(
        $this->tenant->id,
        'Main Warehouse',
        'WH01',
        InventoryLocation::TYPE_WAREHOUSE,
    );
    expect($location)->toBeInstanceOf(InventoryLocation::class);
    expect($location->name)->toBe('Main Warehouse');
    expect($location->code)->toBe('WH01');
    expect($location->type)->toBe(InventoryLocation::TYPE_WAREHOUSE);
    expect($location->is_active)->toBeTrue();
})->group('multi_location_inventory');
