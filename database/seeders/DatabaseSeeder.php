<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Main seeder. Order matters: landlord first, then tenants, then financial integrity.
 *
 * DatabaseSeeder
 *  ├── LandlordSeeder (super-admin, plans, features, tenants, subscriptions, landlord roles)
 *  ├── TenantSeeder (per-tenant: TenantDataSeeder with tenancy initialized)
 *  └── FinancialIntegritySeeder (verify reconciliation per tenant)
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LandlordSeeder::class,
            TenantSeeder::class,
            FinancialIntegritySeeder::class,
        ]);
    }
}
