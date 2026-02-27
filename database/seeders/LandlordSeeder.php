<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Landlord\Models\Domain;
use App\Landlord\Models\Feature;
use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds central (landlord) database: super-admin, plans, features, tenants, subscriptions.
 */
final class LandlordSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuperAdmin();
        $this->seedPlansAndFeatures();
        $this->call(FeaturePlanSeeder::class);
        $this->seedTenantsAndSubscriptions();
        $this->runTenantMigrations();
        $this->seedLandlordRolesAndAssignSuperAdmin();
    }

    private function runTenantMigrations(): void
    {
        Artisan::call('tenants:migrate', ['--force' => true]);
    }

    private function seedSuperAdmin(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
            ]
        );
    }

    private function seedPlansAndFeatures(): void
    {
        Plan::firstOrCreate(
            ['code' => 'basic'],
            [
                'name' => 'Basic',
                'price' => 29.00,
                'price_amount' => 2900,
                'currency' => 'USD',
                'billing_interval' => 'month',
                'stripe_price_id' => 'price_basic_mock',
                'is_active' => true,
            ]
        );

        Plan::firstOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro',
                'price' => 99.00,
                'price_amount' => 9900,
                'currency' => 'USD',
                'billing_interval' => 'month',
                'stripe_price_id' => 'price_pro_mock',
                'is_active' => true,
            ]
        );

        Feature::firstOrCreate(
            ['code' => 'products_limit'],
            ['description' => 'Max products', 'type' => Feature::TYPE_LIMIT]
        );
        Feature::firstOrCreate(
            ['code' => 'multi_currency'],
            ['description' => 'Multi-currency', 'type' => Feature::TYPE_BOOLEAN]
        );
        Feature::firstOrCreate(
            ['code' => 'multi_location_inventory'],
            ['description' => 'Multi-location inventory', 'type' => Feature::TYPE_BOOLEAN]
        );
        Feature::firstOrCreate(
            ['code' => 'advanced_reports'],
            ['description' => 'Advanced reports', 'type' => Feature::TYPE_BOOLEAN]
        );
    }

    private function seedTenantsAndSubscriptions(): void
    {
        $basicPlan = Plan::where('code', 'basic')->first();
        $proPlan = Plan::where('code', 'pro')->first();

        $tenantOne = Tenant::firstOrCreate(
            ['slug' => 'tenant-one'],
            [
                'name' => 'Tenant One',
                'status' => 'active',
                'plan_id' => $basicPlan?->id,
            ]
        );
        Domain::firstOrCreate(
            ['domain' => 'tenant-one.' . config('tenancy.tenant_base_domain')],
            ['tenant_id' => $tenantOne->id]
        );
        if ($basicPlan && !Subscription::where('tenant_id', $tenantOne->id)->exists()) {
            Subscription::create([
                'tenant_id' => $tenantOne->id,
                'plan_id' => $basicPlan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'stripe_subscription_id' => 'sub_seed_' . Str::random(24),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'cancel_at_period_end' => false,
            ]);
        }

        $tenantTwo = Tenant::firstOrCreate(
            ['slug' => 'tenant-two'],
            [
                'name' => 'Tenant Two',
                'status' => 'active',
                'plan_id' => $proPlan?->id,
            ]
        );
        Domain::firstOrCreate(
            ['domain' => 'tenant-two.' . config('tenancy.tenant_base_domain')],
            ['tenant_id' => $tenantTwo->id]
        );
        if ($proPlan && !Subscription::where('tenant_id', $tenantTwo->id)->exists()) {
            Subscription::create([
                'tenant_id' => $tenantTwo->id,
                'plan_id' => $proPlan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'stripe_subscription_id' => 'sub_seed_' . Str::random(24),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'cancel_at_period_end' => false,
            ]);
        }
    }

    private function seedLandlordRolesAndAssignSuperAdmin(): void
    {
        $rolesSeeder = new RolesAndPermissionsSeeder();
        $rolesSeeder->seedLandlordRolesAndPermissions();
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        if ($superAdmin !== null) {
            $rolesSeeder->assignSuperAdminTo($superAdmin);
        }
    }
}
