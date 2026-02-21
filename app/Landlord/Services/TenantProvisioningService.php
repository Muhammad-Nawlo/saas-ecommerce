<?php

namespace App\Landlord\Services;

use App\Landlord\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantProvisioningService
{
    public function provision(
        string $name,
        string $domain,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        ?int $planId = null,
    ): Tenant {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'plan_id' => $planId,
        ]);

        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        $tenant->run(function () use ($adminName, $adminEmail, $adminPassword) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

            $admin = User::query()->create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
            ]);

            $admin->assignRole($ownerRole);
        });

        activity('tenant-provisioning')
            ->performedOn($tenant)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'domain' => $domain,
                'admin_email' => $adminEmail,
            ])
            ->log('tenant.provisioned');

        return $tenant;
    }
}
