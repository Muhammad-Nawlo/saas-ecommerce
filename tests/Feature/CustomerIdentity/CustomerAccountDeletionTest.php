<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerIdentity;

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('account deletion anonymizes customer data', function (): void {
    $tenant = createCustomerTenant();
    tenancy()->initialize($tenant);

    $customer = Customer::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $tenant->id,
        'email' => 'delete@example.com',
        'password' => Hash::make('password'),
        'first_name' => 'Delete',
        'last_name' => 'Me',
        'phone' => '+1234567890',
        'is_active' => true,
    ]);

    $deletionService = app(CustomerDeletionService::class);
    $deletionService->deleteAndAnonymize($customer);

    $customer->refresh();
    expect($customer->email)->not->toBe('delete@example.com');
    expect($customer->email)->toContain('@deleted.local');
    expect($customer->first_name)->toBe('Deleted');
    expect($customer->last_name)->toBe('User');
    expect($customer->phone)->toBeNull();
    expect($customer->trashed())->toBeTrue();
})->group('customer-identity');
