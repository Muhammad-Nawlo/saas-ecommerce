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
 * ReconcileFinancialDataJob
 *
 * Runs FinancialReconciliationService::reconcile() for each tenant. Intended to be run daily via scheduler.
 * Initializes tenancy for each tenant (tenancy()->initialize($tenant)) before reconciling, then ends tenancy.
 * Reconciliation only detects and logs inconsistencies (ledger balanced, invoice total, payments sum); does not auto-fix.
 *
 * Fetches tenants from central DB (Tenant::all()). No financial data written; read-only per tenant DB.
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
