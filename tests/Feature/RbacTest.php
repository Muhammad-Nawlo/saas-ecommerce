<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\TenantPermissions;
use App\Enums\TenantRole;
use App\Landlord\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createAndMigrateTenant(array $attributes = []): Tenant
{
    $tenant = Tenant::create(array_merge(['name' => 'Test Tenant', 'data' => []], $attributes));
    $tenant->run(function (): void {
        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--force' => true,
        ]);
        (new TenantRoleSeeder())->run();
    });
    return $tenant;
}

test('owner can create product', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Owner->value);

    $this->actingAs($user);
    expect(auth()->user()->can(TenantPermissions::CREATE_PRODUCTS))->toBeTrue();
})->group('rbac');

test('staff cannot create product', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Staff->value);

    $this->actingAs($user);
    expect(auth()->user()->can(TenantPermissions::CREATE_PRODUCTS))->toBeFalse();
})->group('rbac');

test('viewer cannot edit order', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Viewer->value);

    $this->actingAs($user);
    expect(auth()->user()->can(TenantPermissions::EDIT_ORDERS))->toBeFalse();
})->group('rbac');

test('role assignment works', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Manager->value);

    $this->actingAs($user);
    expect(auth()->user()->hasRole(TenantRole::Manager->value))->toBeTrue();
    expect(auth()->user()->can(TenantPermissions::EDIT_PRODUCTS))->toBeTrue();
})->group('rbac');

test('super_admin can access landlord admin', function (): void {
    (new \Database\Seeders\LandlordRoleSeeder())->run();
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)->get('/admin')->assertSuccessful();
})->group('rbac');

test('support_agent cannot edit plans', function (): void {
    (new \Database\Seeders\LandlordRoleSeeder())->run();
    $user = User::factory()->create();
    $user->assignRole('support_agent');

    $this->actingAs($user);
    expect(auth()->user()->can('manage plans'))->toBeFalse();
})->group('rbac');

test('finance_admin can view billing', function (): void {
    (new \Database\Seeders\LandlordRoleSeeder())->run();
    $user = User::factory()->create();
    $user->assignRole('finance_admin');

    $this->actingAs($user);
    expect(auth()->user()->can('view subscriptions'))->toBeTrue();
})->group('rbac');
