<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Services\Customer\CustomerPromotionEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Promo Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('first purchase promotion rule: hasPlacedOrder false for new customer', function (): void {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'new@example.com',
        'password' => bcrypt('s'),
        'first_name' => 'New',
        'last_name' => 'Customer',
    ]);
    $service = app(CustomerPromotionEligibilityService::class);
    expect($service->hasPlacedOrder($customer->id, $customer->email))->toBeFalse();
    expect($service->orderCountForCustomer($customer->id, $customer->email))->toBe(0);
})->group('customer-identity');

test('first purchase promotion rule: hasPlacedOrder true after order', function (): void {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'returning@example.com',
        'password' => bcrypt('s'),
        'first_name' => 'Returning',
        'last_name' => 'Customer',
    ]);
    OrderModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $customer->id,
        'customer_email' => $customer->email,
        'status' => 'paid',
        'total_amount' => 1000,
        'currency' => 'USD',
    ]);
    $service = app(CustomerPromotionEligibilityService::class);
    expect($service->hasPlacedOrder($customer->id, $customer->email))->toBeTrue();
    expect($service->orderCountForCustomer($customer->id, $customer->email))->toBe(1);
})->group('customer-identity');
