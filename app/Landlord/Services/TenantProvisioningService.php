<?php

namespace App\Landlord\Services;

use App\Landlord\Models\Tenant;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function create(string $name, string $domain): Tenant
    {
        $tenant = Tenant::create([
            'id' => (string)Str::uuid(),
            'name' => $name,
        ]);

        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        $tenant->run(function () {
            \Artisan::call('tenants:migrate');
        });

        return $tenant;
    }
}
