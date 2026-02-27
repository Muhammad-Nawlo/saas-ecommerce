<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Landlord\Models\Domain;
use App\Landlord\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Add a domain to an existing tenant so the tenant can be identified on that host.
 * Use when you get TenantCouldNotBeIdentifiedOnDomainException (e.g. after visiting tenant-one.saas-ecommerce.test).
 */
final class TenantAddDomainCommand extends Command
{
    protected $signature = 'landlord:tenant-add-domain
                            {tenant : Tenant slug or id}
                            {domain : Full domain (e.g. tenant-one.saas-ecommerce.test)}';

    protected $description = 'Add a domain to a tenant so it can be identified on that host';

    public function handle(): int
    {
        $tenantInput = (string) $this->argument('tenant');
        $domain = strtolower(trim((string) $this->argument('domain')));

        $tenant = Tenant::query()
            ->where('slug', $tenantInput)
            ->orWhere('id', $tenantInput)
            ->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantInput}");

            return self::FAILURE;
        }

        $existing = Domain::where('domain', $domain)->first();
        if ($existing && $existing->tenant_id !== $tenant->id) {
            $this->error("Domain '{$domain}' is already assigned to another tenant.");

            return self::FAILURE;
        }

        Domain::firstOrCreate(
            ['domain' => $domain],
            ['tenant_id' => $tenant->id]
        );

        $this->info("Domain '{$domain}' is now linked to tenant: {$tenant->name} ({$tenant->slug}).");

        return self::SUCCESS;
    }
}
