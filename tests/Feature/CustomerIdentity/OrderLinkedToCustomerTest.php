<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Order Link Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('order can be linked to customer', function (): void {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'orderlink@example.com',
        'password' => bcrypt('secret'),
        'first_name' => 'Order',
        'last_name' => 'Link',
    ]);
    $order = OrderModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'status' => 'pending',
        'total_amount' => 1000,
        'currency' => 'USD',
    ]);
    expect($order->customer_id)->toBe($customer->id);
    expect($order->customer_email)->toBe($customer->email);
})->group('customer-identity');
