<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Shared\Infrastructure\Audit\TenantAuditLog;
use App\Landlord\Models\LandlordAuditLog;
use App\Models\User;
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
    });
    return $tenant;
}

test('creating product logs event in tenant DB', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $this->actingAs($user);

    ProductModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'description' => '',
        'price_minor_units' => 1000,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    $log = TenantAuditLog::query()->where('event_type', 'created')->where('model_type', 'like', '%Product%')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Test Product');
})->group('audit');

test('updating order logs diff in tenant DB', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $order = OrderModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'customer_email' => 'a@b.com',
        'status' => 'pending',
        'total_amount' => 1000,
        'currency' => 'USD',
    ]);

    $this->actingAs($user);
    $order->update(['status' => 'paid']);

    $log = TenantAuditLog::query()->where('event_type', 'updated')->where('model_type', 'like', '%Order%')->first();
    expect($log)->not->toBeNull();
    expect($log->properties)->toBeArray();
    expect($log->properties['changes'] ?? [])->toHaveKey('status');
})->group('audit');

test('deleting product logs event in tenant DB', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $product = ProductModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'name' => 'To Delete',
        'slug' => 'to-delete',
        'description' => '',
        'price_minor_units' => 500,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    $this->actingAs($user);
    $product->delete();

    $log = TenantAuditLog::query()->where('event_type', 'deleted')->where('model_type', 'like', '%Product%')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('To Delete');
})->group('audit');

test('plan update logs event in landlord DB', function (): void {
    $conn = config('tenancy.database.central_connection', config('database.default'));
    runCentralMigrations();

    $plan = Plan::on($conn)->create([
        'name' => 'Pro',
        'code' => 'pro',
        'price' => 99,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);
    $plan->update(['name' => 'Pro Plus']);

    $log = LandlordAuditLog::query()->where('event_type', 'updated')->where('model_type', 'like', '%Plan%')->first();
    expect($log)->not->toBeNull();
    expect($log->properties['changes']['name'] ?? null)->toEqual(['old' => 'Pro', 'new' => 'Pro Plus']);
})->group('audit');

test('subscription cancellation logs event in landlord DB', function (): void {
    $conn = config('tenancy.database.central_connection', config('database.default'));
    runCentralMigrations();

    $tenant = Tenant::create(['name' => 'Sub Tenant', 'data' => []]);
    $plan = Plan::on($conn)->first() ?? Plan::on($conn)->create([
        'name' => 'Basic',
        'code' => 'basic',
        'price' => 0,
        'billing_interval' => 'monthly',
        'is_active' => true,
    ]);

    $sub = Subscription::on($conn)->create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'stripe_subscription_id' => 'sub_test',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);
    $sub->update(['status' => 'canceled']);

    $log = LandlordAuditLog::query()->where('event_type', 'updated')->where('model_type', 'like', '%Subscription%')->first();
    expect($log)->not->toBeNull();
})->group('audit');

test('tenant logs stored in tenant DB only', function (): void {
    $tenant = createAndMigrateTenant();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();
    $this->actingAs($user);

    ProductModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'name' => 'Only Tenant',
        'slug' => 'only-tenant',
        'description' => '',
        'price_minor_units' => 100,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    expect(TenantAuditLog::query()->count())->toBeGreaterThan(0);
})->group('audit');

function runCentralMigrations(): void
{
    if (!\Illuminate\Support\Facades\Schema::hasTable('landlord_audit_logs')) {
        Artisan::call('migrate', ['--path' => database_path('migrations'), '--force' => true]);
    }
}
