<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Landlord\Models\Tenant;
use App\Modules\Financial\Application\Services\FinancialReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs financial reconciliation per tenant. Intended to be run daily via scheduler.
 * Uses tenant context correctly: initializes tenancy for each tenant before reconciling.
 */
final class ReconcileFinancialDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct() {}

    public function handle(FinancialReconciliationService $service): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            try {
                $service->reconcile();
            } finally {
                tenancy()->end();
            }
        }
    }
}
