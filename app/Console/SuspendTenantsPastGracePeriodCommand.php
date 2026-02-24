<?php

declare(strict_types=1);

namespace App\Console;

use App\Landlord\Billing\Infrastructure\Persistence\SubscriptionModel;
use App\Landlord\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Suspends tenants whose subscription has been past_due for more than the grace period (7 days).
 * Run daily via scheduler.
 */
final class SuspendTenantsPastGracePeriodCommand extends Command
{
    protected $signature = 'billing:suspend-past-grace-period';

    protected $description = 'Suspend tenants with subscription past_due longer than 7 days';

    private const int GRACE_PERIOD_DAYS = 7;

    public function handle(): int
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        $cutoff = now()->subDays(self::GRACE_PERIOD_DAYS);

        $subscriptions = SubscriptionModel::on($conn)
            ->where('status', 'past_due')
            ->whereNotNull('past_due_at')
            ->where('past_due_at', '<=', $cutoff)
            ->get();

        $suspended = 0;
        foreach ($subscriptions as $sub) {
            $tenant = Tenant::find($sub->tenant_id);
            if ($tenant === null) {
                continue;
            }
            if (($tenant->status ?? 'active') === 'suspended') {
                continue;
            }
            $tenant->status = 'suspended';
            $tenant->suspended_at = $tenant->suspended_at ?? now();
            $tenant->save();
            $suspended++;
            $this->info("Suspended tenant {$tenant->id}");
        }

        $this->info("Suspended {$suspended} tenant(s) past grace period.");
        return self::SUCCESS;
    }
}
