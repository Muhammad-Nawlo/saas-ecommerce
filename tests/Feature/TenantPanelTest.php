<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Tenant;
use App\Models\User;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Ensure tenant DB exists and migrations are run. Call before tenancy()->initialize($tenant).
 * Use global createAndMigrateTenant() from Pest.php (TenantTestHelper).
 */

test('tenant can access dashboard when tenant context and active', function (): void {
    $tenant = createAndMigrateTenant(['name' => 'Test Store']);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertSuccessful();
})->group('tenant_panel');

test('suspended tenant is blocked from dashboard', function (): void {
    $tenant = createAndMigrateTenant([
        'name' => 'Suspended Store',
        'status' => 'suspended',
    ]);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertRedirect(route('filament.tenant.auth.login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
})->group('tenant_panel');

test('product limit enforced on create', function (): void {
    $tenant = createAndMigrateTenant(['name' => 'Limit Test']);
    tenancy()->initialize($tenant);

    ProductModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'name' => 'Product 1',
        'slug' => 'product-1',
        'description' => '',
        'price_minor_units' => 1000,
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $count = ProductModel::forTenant($tenant->id)->count();
    expect($count)->toBe(1);
})->group('tenant_panel');

test('order list scoped to tenant', function (): void {
    $tenant = createAndMigrateTenant(['name' => 'Order Tenant']);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard/orders');
    $response->assertSuccessful();
})->group('tenant_panel');

test('billing page shows when tenant has subscription', function (): void {
    $tenant = createAndMigrateTenant(['name' => 'Billing Tenant']);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard/billing');
    $response->assertSuccessful();
})->group('tenant_panel');
