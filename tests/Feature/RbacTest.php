<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\TenantPermissions;
use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can create product', function (): void {
    $tenant = createAndMigrateTenant([], true);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Owner->value);

    $this->actingAs($user);
    expect(auth()->user()->can(TenantPermissions::CREATE_PRODUCTS))->toBeTrue();
})->group('rbac');

test('staff cannot create product', function (): void {
    $tenant = createAndMigrateTenant([], true);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Staff->value);

    $this->actingAs($user);
    expect(auth()->user()->can(TenantPermissions::CREATE_PRODUCTS))->toBeFalse();
})->group('rbac');

test('viewer cannot edit order', function (): void {
    $tenant = createAndMigrateTenant([], true);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Viewer->value);

    $this->actingAs($user);
    expect(auth()->user()->can(TenantPermissions::EDIT_ORDERS))->toBeFalse();
})->group('rbac');

test('role assignment works', function (): void {
    $tenant = createAndMigrateTenant([], true);
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $user->assignRole(TenantRole::Manager->value);

    $this->actingAs($user);
    expect(auth()->user()->hasRole(TenantRole::Manager->value))->toBeTrue();
    expect(auth()->user()->can(TenantPermissions::EDIT_PRODUCTS))->toBeTrue();
})->group('rbac');

test('super_admin can access landlord admin', function (): void {
    if (! class_exists(\Filament\Facades\Filament::class)) {
        $this->markTestSkipped('Filament not loaded in testing (backend-only mode).');
    }
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
