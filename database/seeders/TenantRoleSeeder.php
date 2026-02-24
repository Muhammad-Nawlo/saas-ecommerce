<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\TenantPermissions;
use App\Enums\TenantRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds tenant roles and permissions. Run inside tenant context (e.g. TenantDatabaseSeeder).
 * Creates: owner, manager, staff, viewer and assigns permissions.
 */
class TenantRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = collect(TenantPermissions::all())
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]));

        $owner = Role::firstOrCreate(['name' => TenantRole::Owner->value, 'guard_name' => $guard]);
        $owner->syncPermissions($permissions);

        $managerOnlyDeny = [
            TenantPermissions::MANAGE_BILLING,
            TenantPermissions::MANAGE_DOMAIN,
            TenantPermissions::MANAGE_ROLES,
            TenantPermissions::MANAGE_USERS,
        ];
        $manager = Role::firstOrCreate(['name' => TenantRole::Manager->value, 'guard_name' => $guard]);
        $manager->syncPermissions($permissions->filter(fn (Permission $p) => !in_array($p->name, $managerOnlyDeny, true))->values()->all());

        $staff = Role::firstOrCreate(['name' => TenantRole::Staff->value, 'guard_name' => $guard]);
        $staff->syncPermissions([
            TenantPermissions::VIEW_PRODUCTS,
            TenantPermissions::VIEW_ORDERS,
            TenantPermissions::EDIT_ORDERS,
            TenantPermissions::VIEW_CUSTOMERS,
            TenantPermissions::VIEW_INVENTORY,
            TenantPermissions::EDIT_INVENTORY,
            TenantPermissions::VIEW_INVOICES,
        ]);

        $viewer = Role::firstOrCreate(['name' => TenantRole::Viewer->value, 'guard_name' => $guard]);
        $viewer->syncPermissions([
            TenantPermissions::VIEW_PRODUCTS,
            TenantPermissions::VIEW_ORDERS,
            TenantPermissions::VIEW_CUSTOMERS,
            TenantPermissions::VIEW_INVENTORY,
            TenantPermissions::VIEW_INVOICES,
        ]);
    }
}
