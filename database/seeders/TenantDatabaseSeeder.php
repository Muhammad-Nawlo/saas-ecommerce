<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs in tenant context (tenants:seed). Seeds tenant roles and permissions.
 * Assign 'owner' to the tenant creator separately (e.g. after first user is created).
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantRoleSeeder::class,
        ]);
    }
}
