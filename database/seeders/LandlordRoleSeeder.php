<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\LandlordPermissions;
use App\Enums\LandlordRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds landlord (central) roles and permissions. Run on central DB only.
 */
class LandlordRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        Permission::firstOrCreate(['name' => LandlordPermissions::VIEW_PLANS, 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => LandlordPermissions::MANAGE_PLANS, 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => LandlordPermissions::VIEW_TENANTS, 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => LandlordPermissions::MANAGE_TENANTS, 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => LandlordPermissions::VIEW_SUBSCRIPTIONS, 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => LandlordPermissions::MANAGE_SUBSCRIPTIONS, 'guard_name' => $guard]);

        $superAdmin = Role::firstOrCreate(['name' => LandlordRole::SuperAdmin->value, 'guard_name' => $guard]);
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->pluck('name')->all());

        $supportAgent = Role::firstOrCreate(['name' => LandlordRole::SupportAgent->value, 'guard_name' => $guard]);
        $supportAgent->syncPermissions([LandlordPermissions::VIEW_TENANTS]);

        $financeAdmin = Role::firstOrCreate(['name' => LandlordRole::FinanceAdmin->value, 'guard_name' => $guard]);
        $financeAdmin->syncPermissions([
            LandlordPermissions::VIEW_PLANS,
            LandlordPermissions::MANAGE_PLANS,
            LandlordPermissions::VIEW_TENANTS,
            LandlordPermissions::VIEW_SUBSCRIPTIONS,
            LandlordPermissions::MANAGE_SUBSCRIPTIONS,
        ]);
    }
}
