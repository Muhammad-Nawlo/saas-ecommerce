<?php

declare(strict_types=1);

use App\Landlord\Models\Feature;
use App\Landlord\Models\Plan;
use App\Landlord\Models\PlanFeature;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Landlord\Services\FeatureResolver;
use App\Modules\Shared\Domain\Exceptions\NoActiveSubscriptionException;
use Illuminate\Support\Facades\Cache;

uses()->group('plan_limits');

$centralConnection = null;

beforeEach(function (): void {
    $GLOBALS['centralConnection'] = config('tenancy.database.central_connection', config('database.default'));
});

test('pro plan getLimit returns 5000 for products_limit', function (): void {
    $conn = $GLOBALS['centralConnection'];
    $plan = Plan::on($conn)->firstOrCreate(
        ['code' => 'pro'],
        ['name' => 'Pro', 'price' => 99, 'billing_interval' => 'monthly']
    );
    $feature = Feature::on($conn)->firstOrCreate(
        ['code' => 'products_limit'],
        ['description' => 'Max products', 'type' => Feature::TYPE_LIMIT]
    );
    PlanFeature::on($conn)->firstOrCreate(
        ['plan_id' => $plan->id, 'feature_id' => $feature->id],
        ['value' => '5000']
    );
    $tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Pro Tenant',
        'data' => [],
    ]);
    Subscription::on($conn)->updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);
    Cache::flush();

    $resolver = app(FeatureResolver::class);
    expect($resolver->getLimit('products_limit'))->toBe(5000);
});

test('enterprise plan getLimit returns null for products_limit', function (): void {
    $conn = $GLOBALS['centralConnection'];
    $plan = Plan::on($conn)->firstOrCreate(
        ['code' => 'enterprise'],
        ['name' => 'Enterprise', 'price' => 299, 'billing_interval' => 'yearly']
    );
    $feature = Feature::on($conn)->firstOrCreate(
        ['code' => 'products_limit'],
        ['description' => 'Max products', 'type' => Feature::TYPE_LIMIT]
    );
    PlanFeature::on($conn)->firstOrCreate(
        ['plan_id' => $plan->id, 'feature_id' => $feature->id],
        ['value' => '-1']
    );
    $tenant = Tenant::first() ?? Tenant::create(['name' => 'Ent Tenant', 'data' => []]);
    Subscription::on($conn)->updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_ent_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]
    );

    tenancy()->initialize($tenant);
    Cache::flush();

    $resolver = app(FeatureResolver::class);
    expect($resolver->getLimit('products_limit'))->toBeNull();
});

test('tenant with no active subscription throws when getLimit', function (): void {
    $tenant = Tenant::first() ?? Tenant::create(['name' => 'No Sub Tenant', 'data' => []]);
    Subscription::on($GLOBALS['centralConnection'])
        ->where('tenant_id', $tenant->id)
        ->delete();
    tenancy()->initialize($tenant);
    Cache::flush();

    $resolver = app(FeatureResolver::class);
    $resolver->getLimit('products_limit');
})->throws(NoActiveSubscriptionException::class);

test('hasFeature returns false for boolean feature with value 0', function (): void {
    $conn = $GLOBALS['centralConnection'];
    $plan = Plan::on($conn)->firstOrCreate(
        ['code' => 'starter'],
        ['name' => 'Starter', 'price' => 0, 'billing_interval' => 'monthly']
    );
    $feature = Feature::on($conn)->firstOrCreate(
        ['code' => 'custom_domain'],
        ['description' => 'Custom domain', 'type' => Feature::TYPE_BOOLEAN]
    );
    PlanFeature::on($conn)->firstOrCreate(
        ['plan_id' => $plan->id, 'feature_id' => $feature->id],
        ['value' => '0']
    );
    $tenant = Tenant::first() ?? Tenant::create(['name' => 'Starter Tenant', 'data' => []]);
    Subscription::on($conn)->updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);
    Cache::flush();

    $resolver = app(FeatureResolver::class);
    expect($resolver->hasFeature('custom_domain'))->toBeFalse();
});

test('hasFeature returns true for boolean feature with value 1', function (): void {
    $conn = $GLOBALS['centralConnection'];
    $plan = Plan::on($conn)->firstOrCreate(
        ['code' => 'pro'],
        ['name' => 'Pro', 'price' => 99, 'billing_interval' => 'monthly']
    );
    $feature = Feature::on($conn)->firstOrCreate(
        ['code' => 'custom_domain'],
        ['description' => 'Custom domain', 'type' => Feature::TYPE_BOOLEAN]
    );
    PlanFeature::on($conn)->firstOrCreate(
        ['plan_id' => $plan->id, 'feature_id' => $feature->id],
        ['value' => '1']
    );
    $tenant = Tenant::first() ?? Tenant::create(['name' => 'Pro Tenant', 'data' => []]);
    Subscription::on($conn)->updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);
    Cache::flush();

    $resolver = app(FeatureResolver::class);
    expect($resolver->hasFeature('custom_domain'))->toBeTrue();
});
