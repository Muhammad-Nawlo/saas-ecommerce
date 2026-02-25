<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Landlord\Models\Tenant;
use App\Modules\Financial\Application\Services\FinancialReconciliationService;
use Illuminate\Database\Seeder;

/**
 * Runs financial reconciliation verify() for each tenant. Run after TenantSeeder.
 * Throws if any tenant has mismatches (ledger unbalanced, invoice total mismatch, payments sum mismatch).
 */
final class FinancialIntegritySeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();
        $reconciliation = app(FinancialReconciliationService::class);

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $reconciliation->verify((string) $tenant->getTenantKey());
            tenancy()->end();
        }
    }
}
