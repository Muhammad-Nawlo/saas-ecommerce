<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Landlord\Models\Tenant;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Initializes each tenant and runs TenantDataSeeder. Run after LandlordSeeder.
 */
final class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->command?->warn('TenantSeeder: No tenants found. Run LandlordSeeder first.');
            return;
        }

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $this->command?->info('Seeding tenant: ' . $tenant->name);
            $this->call(TenantDataSeeder::class);
            tenancy()->end();
        }
    }
}
