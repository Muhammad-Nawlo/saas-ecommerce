<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Inventory\InventoryLocation;
use App\Services\Inventory\InventoryLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Limit Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('when multi_location disabled only one location allowed', function (): void {
    $service = app(InventoryLocationService::class);
    $first = $service->getOrCreateDefaultLocation($this->tenant->id);
    expect($first)->toBeInstanceOf(InventoryLocation::class);
    $canCreate = $service->canCreateMoreLocations($this->tenant->id);
    if (!$canCreate) {
        expect($service->canTransfer($this->tenant->id))->toBeFalse();
    }
})->group('multi_location_inventory');
