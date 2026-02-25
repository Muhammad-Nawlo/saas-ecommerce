<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Modules\Financial\Application\Services\FinancialReconciliationService;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use Illuminate\Console\Command;

/**
 * Investor-grade integrity check: snapshot hashes, ledger balance, invoice vs financial order consistency.
 * Run per-tenant. Outputs PASS or list of mismatches. Does not auto-correct.
 */
final class SystemIntegrityCheckCommand extends Command
{
    protected $signature = 'system:integrity-check
                            {--tenant= : Tenant ID to check (default: all tenants)}';

    protected $description = 'Verify financial snapshot hashes, ledger balance, and invoice/order consistency';

    public function __construct(
        private FinancialReconciliationService $reconciliation,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId !== null
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenant(s) to check.');
            return self::SUCCESS;
        }

        $allMismatches = [];
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $tenant->run(function () use ($tenant, &$allMismatches): void {
                $mismatches = $this->runChecksForTenant((string) $tenant->id);
                foreach ($mismatches as $m) {
                    $m['tenant_id'] = $tenant->id;
                    $allMismatches[] = $m;
                }
            });
        }

        if ($allMismatches === []) {
            $this->info('PASS');
            return self::SUCCESS;
        }

        $this->error('INTEGRITY CHECK FAILED');
        $this->table(
            ['tenant_id', 'type', 'details'],
            array_map(fn (array $m): array => [
                $m['tenant_id'] ?? '',
                $m['mismatch_type'] ?? $m['type'] ?? 'unknown',
                json_encode($m, JSON_UNESCAPED_SLASHES),
            ], $allMismatches),
        );
        return self::FAILURE;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runChecksForTenant(string $tenantId): array
    {
        $mismatches = [];

        foreach (FinancialOrder::whereNotNull('locked_at')->whereNotNull('snapshot_hash')->get() as $order) {
            if (! $order->verifySnapshotIntegrity()) {
                $mismatches[] = [
                    'mismatch_type' => 'snapshot_hash_financial_order',
                    'financial_order_id' => $order->id,
                    'order_number' => $order->order_number,
                ];
            }
        }

        foreach (Invoice::whereNotNull('snapshot_hash')->where('status', '!=', Invoice::STATUS_DRAFT)->get() as $invoice) {
            if (! $invoice->verifySnapshotIntegrity()) {
                $mismatches[] = [
                    'mismatch_type' => 'snapshot_hash_invoice',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ];
            }
        }

        foreach (PaymentModel::where('status', PaymentModel::STATUS_SUCCEEDED)->whereNotNull('snapshot_hash')->get() as $payment) {
            if (! $payment->verifySnapshotIntegrity()) {
                $mismatches[] = [
                    'mismatch_type' => 'snapshot_hash_payment',
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ];
            }
        }

        $reconcileIssues = $this->reconciliation->reconcile($tenantId);
        foreach ($reconcileIssues as $issue) {
            $mismatches[] = $issue;
        }

        return $mismatches;
    }
}
