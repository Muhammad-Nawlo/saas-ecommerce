<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Login Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('login success returns token', function (): void {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'user@example.com',
        'password' => bcrypt('secret123'),
        'first_name' => 'Test',
        'last_name' => 'User',
        'is_active' => true,
    ]);
    $response = $this->postJson('/api/v1/customer/login', [
        'email' => 'user@example.com',
        'password' => 'secret123',
    ]);
    $response->assertOk();
    $response->assertJsonStructure(['token', 'customer']);
})->group('customer-identity');

test('login failure returns generic error', function (): void {
    $response = $this->postJson('/api/v1/customer/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'wrong',
    ]);
    $response->assertStatus(422);
})->group('customer-identity');

test('inactive customer cannot login', function (): void {
    Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'inactive@example.com',
        'password' => bcrypt('secret'),
        'first_name' => 'In',
        'last_name' => 'Active',
        'is_active' => false,
    ]);
    $response = $this->postJson('/api/v1/customer/login', [
        'email' => 'inactive@example.com',
        'password' => 'secret',
    ]);
    $response->assertStatus(422);
})->group('customer-identity');
