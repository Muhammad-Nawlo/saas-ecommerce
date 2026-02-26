<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Services\Customer\LinkGuestOrdersToCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Guest Link Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('guest orders can be linked to customer by email', function (): void {
    $email = 'guest@example.com';
    OrderModel::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'customer_id' => null,
        'customer_email' => $email,
        'status' => 'pending',
        'total_amount' => 500,
        'currency' => 'USD',
    ]);
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => $email,
        'password' => bcrypt('secret'),
        'first_name' => 'Guest',
        'last_name' => 'User',
    ]);
    $service = app(LinkGuestOrdersToCustomerService::class);
    $linked = $service->linkByEmail($customer);
    expect($linked)->toBe(1);
    $order = OrderModel::forTenant($this->tenant->id)->where('customer_email', $email)->first();
    expect($order->customer_id)->toBe($customer->id);
})->group('customer-identity');
