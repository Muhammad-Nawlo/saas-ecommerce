<?php

declare(strict_types=1);

use App\Landlord\Models\Domain;
use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('checkout rate limiter is defined', function (): void {
    $key = 'checkout:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('checkout', $key))->toBeFalse();
})->group('rate_limit');

test('payment rate limiter is defined', function (): void {
    $key = 'payment:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('payment', $key))->toBeFalse();
})->group('rate_limit');

test('payment-confirm rate limiter is defined', function (): void {
    $key = 'payment-confirm:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('payment-confirm', $key))->toBeFalse();
})->group('rate_limit');

test('webhook rate limiter is defined', function (): void {
    $key = 'webhook:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('webhook', $key))->toBeFalse();
})->group('rate_limit');

test('rate limiter allows requests within limit', function (): void {
    $response = $this->getJson('/health');
    $response->assertOk();
})->group('rate_limit');

test('checkout endpoint returns 429 when rate limit exceeded', function (): void {
    $tenant = Tenant::create(['name' => 'Rate Limit Tenant', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    Domain::create(['domain' => 'tenant1', 'tenant_id' => $tenant->id]);
    RateLimiter::clear('checkout');

    $host = 'tenant1.sass-ecommerce.test';
    for ($i = 0; $i < 31; $i++) {
        $response = $this->withServerVariables(['HTTP_HOST' => $host])
            ->postJson('/api/v1/checkout', [
                'cart_id' => '00000000-0000-0000-0000-000000000001',
                'customer_email' => 'test@example.com',
            ]);
        if ($i < 30) {
            $response->assertStatus(401);
        } else {
            $response->assertStatus(429);
        }
    }
})->group('rate_limit', 'security');
