<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Rate Limit Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
    RateLimiter::clear('customer-login');
});

test('login is rate limited', function (): void {
    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/v1/customer/login', [
            'email' => 'any@example.com',
            'password' => 'wrong',
        ]);
        if ($i < 5) {
            $response->assertStatus(422);
        } else {
            $response->assertStatus(429);
        }
    }
})->group('customer-identity');
