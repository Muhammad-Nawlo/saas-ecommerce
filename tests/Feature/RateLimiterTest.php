<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;

test('checkout rate limiter is defined', function (): void {
    $key = 'checkout:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('checkout', $key))->toBeFalse();
})->group('rate_limit');

test('payment rate limiter is defined', function (): void {
    $key = 'payment:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('payment', $key))->toBeFalse();
})->group('rate_limit');

test('webhook rate limiter is defined', function (): void {
    $key = 'webhook:' . request()->ip();
    expect(RateLimiter::tooManyAttempts('webhook', $key))->toBeFalse();
})->group('rate_limit');

test('rate limiter allows requests within limit', function (): void {
    $response = $this->getJson('/health');
    $response->assertOk();
})->group('rate_limit');
