<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Domain;
use App\Landlord\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Auth Test Tenant', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    Domain::create(['domain' => 'tenant1', 'tenant_id' => $this->tenant->id]);
});

test('unauthenticated POST to checkout returns 401', function (): void {
    $this->withServerVariables(['HTTP_HOST' => 'tenant1.sass-ecommerce.test'])
        ->postJson('/api/v1/checkout', [
            'cart_id' => '00000000-0000-0000-0000-000000000001',
            'customer_email' => 'test@example.com',
        ])
        ->assertStatus(401);
})->group('security');

test('unauthenticated POST to orders returns 401', function (): void {
    $this->withServerVariables(['HTTP_HOST' => 'tenant1.sass-ecommerce.test'])
        ->postJson('/api/v1/orders', [])
        ->assertStatus(401);
})->group('security');

test('unauthenticated POST to payments returns 401', function (): void {
    $this->withServerVariables(['HTTP_HOST' => 'tenant1.sass-ecommerce.test'])
        ->postJson('/api/v1/payments', [
            'order_id' => '00000000-0000-0000-0000-000000000001',
            'amount_cents' => 1000,
            'currency' => 'USD',
        ])
        ->assertStatus(401);
})->group('security');

test('unauthenticated POST to create inventory location returns 401', function (): void {
    tenancy()->initialize($this->tenant);
    $productId = \Illuminate\Support\Str::uuid()->toString();
    tenancy()->end();

    $this->withServerVariables(['HTTP_HOST' => 'tenant1.sass-ecommerce.test'])
        ->postJson('/api/v1/inventory', [
            'product_id' => $productId,
            'location_id' => '00000000-0000-0000-0000-000000000001',
            'quantity' => 10,
        ])
        ->assertStatus(401);
})->group('security');

test('authenticated tenant user can reach protected endpoint', function (): void {
    tenancy()->initialize($this->tenant);
    $user = User::create([
        'name' => 'Test User',
        'email' => 'user@tenant.test',
        'password' => bcrypt('password'),
        'is_super_admin' => false,
    ]);
    $token = $user->createToken('test')->plainTextToken;
    tenancy()->end();

    $response = $this->withServerVariables(['HTTP_HOST' => 'tenant1.sass-ecommerce.test'])
        ->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/reports/revenue');

    $response->assertOk();
})->group('security');

test('landlord subscription show requires authentication', function (): void {
    $this->getJson('/api/landlord/subscriptions/' . $this->tenant->id)
        ->assertStatus(401);
})->group('security');
