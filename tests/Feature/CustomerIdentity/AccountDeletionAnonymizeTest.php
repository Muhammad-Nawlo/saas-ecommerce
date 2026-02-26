<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Delete Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('account deletion anonymizes data', function (): void {
    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'delete-me@example.com',
        'password' => bcrypt('secret'),
        'first_name' => 'Delete',
        'last_name' => 'Me',
        'phone' => '+1234567890',
    ]);
    $id = $customer->id;
    $service = app(CustomerDeletionService::class);
    $service->deleteAndAnonymize($customer);
    $deleted = Customer::withTrashed()->find($id);
    expect($deleted)->not->toBeNull();
    expect($deleted->deleted_at)->not->toBeNull();
    expect($deleted->email)->not->toBe('delete-me@example.com');
    expect(str_contains($deleted->email, '@deleted.local'))->toBeTrue();
    expect($deleted->first_name)->toBe('Deleted');
    expect($deleted->last_name)->toBe('User');
    expect($deleted->phone)->toBeNull();
})->group('customer-identity');
