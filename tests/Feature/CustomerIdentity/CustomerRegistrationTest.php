<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Test Store', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--force' => true,
        ]);
    });
    tenancy()->initialize($this->tenant);
});

test('customer can register', function (): void {
    $response = $this->postJson('/api/v1/customer/register', [
        'email' => 'customer@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
    $response->assertStatus(201);
    $response->assertJsonStructure(['token', 'customer' => ['id', 'email', 'first_name', 'last_name']]);
    expect(Customer::forTenant($this->tenant->id)->where('email', 'customer@example.com')->exists())->toBeTrue();
})->group('customer-identity');

test('customer registration requires unique email per tenant', function (): void {
    Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'existing@example.com',
        'password' => bcrypt('secret'),
        'first_name' => 'A',
        'last_name' => 'B',
    ]);
    $response = $this->postJson('/api/v1/customer/register', [
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'X',
        'last_name' => 'Y',
    ]);
    $response->assertStatus(422);
})->group('customer-identity');
