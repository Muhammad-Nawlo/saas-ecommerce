<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Verification Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('customer has email_verified_at field', function (): void {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'verify@example.com',
        'password' => bcrypt('s'),
        'first_name' => 'Verify',
        'last_name' => 'User',
        'email_verified_at' => null,
    ]);
    expect($customer->email_verified_at)->toBeNull();
    $customer->update(['email_verified_at' => now()]);
    $customer->refresh();
    expect($customer->email_verified_at)->not->toBeNull();
})->group('customer-identity');
