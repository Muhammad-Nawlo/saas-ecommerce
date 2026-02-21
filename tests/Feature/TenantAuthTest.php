<?php

use App\Landlord\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 401 for protected tenant auth endpoint without sanctum token', function () {
    $service = app(TenantProvisioningService::class);

    $service->provision(
        name: 'Secure Tenant',
        domain: 'secure.localhost',
        adminName: 'Secure Owner',
        adminEmail: 'owner@secure.test',
        adminPassword: 'password',
    );

    $this->withServerVariables(['HTTP_HOST' => 'secure.localhost'])
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('allows tenant login and protected endpoint access with bearer token', function () {
    $service = app(TenantProvisioningService::class);

    $service->provision(
        name: 'Auth Tenant',
        domain: 'auth.localhost',
        adminName: 'Auth Owner',
        adminEmail: 'owner@auth.test',
        adminPassword: 'password',
    );

    $login = $this->withServerVariables(['HTTP_HOST' => 'auth.localhost'])
        ->postJson('/api/v1/auth/login', [
            'email' => 'owner@auth.test',
            'password' => 'password',
            'device_name' => 'pest-suite',
        ])
        ->assertOk();

    $token = $login->json('token');

    expect($token)->toBeString()->not->toBe('');

    $this->withServerVariables(['HTTP_HOST' => 'auth.localhost'])
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('email', 'owner@auth.test');
});
