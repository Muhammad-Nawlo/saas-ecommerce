<?php

use App\Landlord\Services\TenantProvisioningService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('provisions a tenant database with roles and admin user', function () {
    $service = app(TenantProvisioningService::class);

    $tenant = $service->provision(
        name: 'Acme Store',
        domain: 'acme.localhost',
        adminName: 'Acme Owner',
        adminEmail: 'owner@acme.test',
        adminPassword: 'password',
    );

    $tenant->run(function () {
        expect(Schema::hasTable('users'))->toBeTrue();
        expect(Schema::hasTable('roles'))->toBeTrue();

        $admin = User::where('email', 'owner@acme.test')->first();

        expect($admin)->not->toBeNull();
        expect($admin->hasRole('owner'))->toBeTrue();
        expect(Role::where('name', 'admin')->exists())->toBeTrue();
    });
});
