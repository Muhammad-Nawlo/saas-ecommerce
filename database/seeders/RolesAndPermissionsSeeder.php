<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds roles and permissions for Landlord (central) and Tenant (per-tenant).
 * - Landlord: run when no tenant context (super-admin, landlord-manager).
 * - Tenant: run after tenancy()->initialize($tenant) (tenant-admin, manager, accountant, customer).
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD_WEB = 'web';

    /**
     * Clear permission cache. Skips when in tenant context and cache store is database (tenant DB may have no cache table).
     */
    private static function clearPermissionCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
            // Tenant DB may not have cache table when using database cache driver.
        }
    }

    /** Landlord permission names (central DB). */
    private const LANDLORD_PERMISSIONS = [
        'manage tenants',
        'manage plans',
        'manage billing',
        'view landlord reports',
        'manage subscriptions',
    ];

    /** Tenant permission names (tenant DB). */
    private const TENANT_PERMISSIONS = [
        'manage products',
        'manage orders',
        'manage financial',
        'manage invoices',
        'manage ledger',
        'manage inventory',
        'manage billing',
        'view reports',
    ];

    public function run(): void
    {
        if (tenant() !== null) {
            $this->seedTenantRolesAndPermissions();
        } else {
            $this->seedLandlordRolesAndPermissions();
        }
    }

    /**
     * Seed landlord (central) roles and permissions. Call from LandlordSeeder.
     */
    public function seedLandlordRolesAndPermissions(): void
    {
        self::clearPermissionCache();
        foreach (self::LANDLORD_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD_WEB]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => self::GUARD_WEB]);
        $superAdmin->givePermissionTo(Permission::all());

        $landlordManager = Role::firstOrCreate(['name' => 'landlord_manager', 'guard_name' => self::GUARD_WEB]);
        $landlordManager->givePermissionTo([
            'manage tenants', 'manage plans', 'manage billing', 'view landlord reports', 'manage subscriptions',
        ]);
    }

    /**
     * Assign super-admin role to the given user (central DB). Call after seedLandlordRolesAndPermissions.
     */
    public function assignSuperAdminTo(User $user): void
    {
        $user->assignRole('super_admin');
    }

    /**
     * Seed tenant roles and permissions. Call from TenantDataSeeder when tenancy is initialized.
     */
    public function seedTenantRolesAndPermissions(): void
    {
        self::clearPermissionCache();
        $originalCacheStore = config('permission.cache.store');
        config(['permission.cache.store' => 'array']);
        try {
            $this->createTenantPermissionsAndRoles();
        } finally {
            config(['permission.cache.store' => $originalCacheStore]);
        }
    }

    private function createTenantPermissionsAndRoles(): void
    {
        foreach (self::TENANT_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD_WEB]);
        }

        $tenantAdmin = Role::firstOrCreate(['name' => 'tenant-admin', 'guard_name' => self::GUARD_WEB]);
        $tenantAdmin->givePermissionTo(Permission::all());

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => self::GUARD_WEB]);
        $manager->givePermissionTo([
            'manage products', 'manage orders', 'manage inventory', 'view reports',
        ]);

        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => self::GUARD_WEB]);
        $accountant->givePermissionTo([
            'manage financial', 'manage invoices', 'manage ledger', 'view reports',
        ]);

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => self::GUARD_WEB]);
        // Customer role has no dashboard permissions; used for storefront identity.
    }

    /**
     * Assign tenant roles to users. Call with tenant initialized.
     * Writes to tenant DB model_has_roles (users live in central).
     *
     * @param array{tenant_admin: User, manager: User, accountant: User} $users
     */
    public function assignTenantRoles(array $users): void
    {
        $tenantConnection = config('database.default');
        $table = config('permission.table_names.model_has_roles');
        $modelKey = config('permission.column_names.model_morph_key', 'model_id');

        $assign = function (User $user, string $roleName) use ($tenantConnection, $table, $modelKey): void {
            $role = Role::on($tenantConnection)->where('name', $roleName)->where('guard_name', self::GUARD_WEB)->first();
            if ($role === null) {
                return;
            }
            $exists = DB::connection($tenantConnection)->table($table)
                ->where($modelKey, $user->id)
                ->where('model_type', User::class)
                ->where('role_id', $role->id)
                ->exists();
            if (!$exists) {
                DB::connection($tenantConnection)->table($table)->insert([
                    'role_id' => $role->id,
                    'model_type' => User::class,
                    $modelKey => $user->id,
                ]);
            }
        };

        if (isset($users['tenant_admin'])) {
            $assign($users['tenant_admin'], 'tenant-admin');
        }
        if (isset($users['manager'])) {
            $assign($users['manager'], 'manager');
        }
        if (isset($users['accountant'])) {
            $assign($users['accountant'], 'accountant');
        }
    }
}
