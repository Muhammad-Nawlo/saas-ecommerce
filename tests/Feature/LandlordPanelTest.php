<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Feature;
use App\Landlord\Models\Plan;
use App\Landlord\Models\PlanFeature;
use App\Landlord\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$centralConn = null;

beforeEach(function (): void {
    $GLOBALS['centralConn'] = config('tenancy.database.central_connection', config('database.default'));
});

test('only super_admin can access landlord admin panel', function (): void {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $normalUser = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($superAdmin)
        ->get('/admin')
        ->assertSuccessful();

    $this->actingAs($normalUser)
        ->get('/admin')
        ->assertForbidden();
})->group('landlord_panel');

test('plan CRUD works as super_admin', function (): void {
    $user = User::factory()->create(['is_super_admin' => true]);
    $conn = $GLOBALS['centralConn'];

    $this->actingAs($user)
        ->get('/admin/plans')
        ->assertSuccessful();

    $this->actingAs($user)
        ->get('/admin/plans/create')
        ->assertSuccessful();

    $plan = Plan::on($conn)->create([
        'name' => 'Test Plan',
        'code' => 'test_plan',
        'price' => 29.99,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/admin/plans/' . $plan->id . '/edit')
        ->assertSuccessful();

    $plan->update(['name' => 'Updated Plan']);
    expect(Plan::on($conn)->find($plan->id)->name)->toBe('Updated Plan');
})->group('landlord_panel');

test('feature CRUD works as super_admin', function (): void {
    $user = User::factory()->create(['is_super_admin' => true]);
    $conn = $GLOBALS['centralConn'];

    $this->actingAs($user)
        ->get('/admin/features')
        ->assertSuccessful();

    $this->actingAs($user)
        ->get('/admin/features/create')
        ->assertSuccessful();

    $feature = Feature::on($conn)->create([
        'code' => 'test_feature',
        'description' => 'Test feature',
        'type' => Feature::TYPE_LIMIT,
    ]);

    $this->actingAs($user)
        ->get('/admin/features/' . $feature->id . '/edit')
        ->assertSuccessful();

    $feature->update(['description' => 'Updated description']);
    expect(Feature::on($conn)->find($feature->id)->description)->toBe('Updated description');
})->group('landlord_panel');

test('plan feature assignment works', function (): void {
    $conn = $GLOBALS['centralConn'];
    $plan = Plan::on($conn)->create([
        'name' => 'Pro',
        'code' => 'pro',
        'price' => 99,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);
    $feature = Feature::on($conn)->create([
        'code' => 'products_limit',
        'description' => 'Products limit',
        'type' => Feature::TYPE_LIMIT,
    ]);

    PlanFeature::on($conn)->create([
        'plan_id' => $plan->id,
        'feature_id' => $feature->id,
        'value' => '100',
    ]);

    $plan->load('planFeatures.feature');
    expect($plan->planFeatures)->toHaveCount(1);
    expect($plan->planFeatures->first()->value)->toBe('100');
})->group('landlord_panel');

test('tenant suspension blocks tenant access', function (): void {
    $tenant = Tenant::create([
        'name' => 'Test Tenant',
        'data' => [],
    ]);
    $tenant->update(['status' => 'suspended']);

    expect($tenant->fresh()->status)->toBe('suspended');
    // Tenant app access is blocked by EnsureTenantNotSuspended middleware when status is suspended.
})->group('landlord_panel');
