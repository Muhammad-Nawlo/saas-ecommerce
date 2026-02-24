<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Landlord\Models\StripeEvent;
use App\Landlord\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prunes old audit and movement records per config('retention.*').
 * Financial records (orders, payments, invoices) are never pruned.
 */
final class RetentionPruneCommand extends Command
{
    protected $signature = 'retention:prune
                            {--dry-run : List what would be pruned without deleting}';

    protected $description = 'Prune tenant audit logs, inventory movements, and Stripe events older than retention config';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $auditDays = config('retention.audit_days', 365);
        $movementDays = config('retention.inventory_movement_days', 365);
        $stripeDays = config('retention.stripe_events_days', 90);
        $auditBefore = now()->subDays($auditDays);
        $movementBefore = now()->subDays($movementDays);
        $stripeBefore = now()->subDays($stripeDays);

        if ($dryRun) {
            $this->info('Dry run — no records will be deleted.');
        }

        $tenants = Tenant::all();
        $totalAudit = 0;
        $totalMovement = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($auditBefore, $movementBefore, $dryRun, &$totalAudit, &$totalMovement): void {
                if (Schema::hasTable('tenant_audit_logs')) {
                    $q = DB::table('tenant_audit_logs')->where('created_at', '<', $auditBefore);
                    $count = $q->count();
                    $totalAudit += $count;
                    if (!$dryRun && $count > 0) {
                        $q->delete();
                    }
                }
                if (Schema::hasTable('inventory_movements')) {
                    $q = DB::table('inventory_movements')->where('created_at', '<', $movementBefore);
                    $count = $q->count();
                    $totalMovement += $count;
                    if (!$dryRun && $count > 0) {
                        $q->delete();
                    }
                }
            });
        }

        $this->info("Tenant audit logs (older than {$auditDays} days): {$totalAudit} " . ($dryRun ? 'would be pruned' : 'pruned'));
        $this->info("Inventory movements (older than {$movementDays} days): {$totalMovement} " . ($dryRun ? 'would be pruned' : 'pruned'));

        if (class_exists(StripeEvent::class) && \Illuminate\Support\Facades\Schema::hasTable('stripe_events')) {
            $stripeQ = StripeEvent::where('created_at', '<', $stripeBefore);
            $stripeCount = $stripeQ->count();
            if (!$dryRun && $stripeCount > 0) {
                $stripeQ->delete();
            }
            $this->info("Stripe events (older than {$stripeDays} days): {$stripeCount} " . ($dryRun ? 'would be pruned' : 'pruned'));
        }

        return self::SUCCESS;
    }
}
