<?php

declare(strict_types=1);

namespace App\Console;

use App\Landlord\Models\LandlordAuditLog;
use App\Landlord\Models\Tenant;
use App\Modules\Shared\Infrastructure\Audit\TenantAuditLog;
use Illuminate\Console\Command;

/**
 * Prunes old audit log entries. Tenant: 180 days. Landlord: 365 days.
 * Schedule in app/Console/Kernel.php: audit:prune daily.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune
                            {--tenant-days= : Override tenant retention days}
                            {--landlord-days= : Override landlord retention days}';

    protected $description = 'Prune audit logs older than retention period';

    public function handle(): int
    {
        $tenantDays = (int) ($this->option('tenant-days') ?? config('audit.retention_days.tenant', 180));
        $landlordDays = (int) ($this->option('landlord-days') ?? config('audit.retention_days.landlord', 365));

        $tenantCutoff = now()->subDays($tenantDays);
        $landlordCutoff = now()->subDays($landlordDays);

        $tenantTotal = 0;
        Tenant::all()->each(function (Tenant $tenant) use ($tenantCutoff, &$tenantTotal): void {
            $tenant->run(function () use ($tenantCutoff, &$tenantTotal): void {
                $deleted = TenantAuditLog::query()->where('created_at', '<', $tenantCutoff)->delete();
                $tenantTotal += $deleted;
            });
        });

        $this->info("Pruned {$tenantTotal} tenant audit log(s) older than {$tenantDays} days.");

        $landlordDeleted = LandlordAuditLog::query()
            ->where('created_at', '<', $landlordCutoff)
            ->delete();

        $this->info("Pruned {$landlordDeleted} landlord audit log(s) older than {$landlordDays} days.");

        return self::SUCCESS;
    }
}
