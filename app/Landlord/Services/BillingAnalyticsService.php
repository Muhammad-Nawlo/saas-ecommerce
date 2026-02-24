<?php

declare(strict_types=1);

namespace App\Landlord\Services;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use Illuminate\Support\Facades\Cache;

/**
 * SaaS billing analytics (landlord). MRR, churn, active tenants, plan distribution.
 */
final readonly class BillingAnalyticsService
{
    private const CACHE_TTL = 300;

    public function mrr(): float
    {
        $key = 'billing_analytics:mrr';
        return (float) Cache::remember($key, self::CACHE_TTL, function (): float {
            $connection = config('tenancy.database.central_connection', config('database.default'));
            return (float) Subscription::on($connection)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->with('plan')
                ->get()
                ->sum(fn ($s) => (float) ($s->plan->price ?? 0));
        });
    }

    public function activeSubscriptionsCount(): int
    {
        $key = 'billing_analytics:active_subscriptions';
        return (int) Cache::remember($key, self::CACHE_TTL, function (): int {
            $connection = config('tenancy.database.central_connection', config('database.default'));
            return Subscription::on($connection)->where('status', Subscription::STATUS_ACTIVE)->count();
        });
    }

    public function activeTenantsCount(): int
    {
        $key = 'billing_analytics:active_tenants';
        return (int) Cache::remember($key, self::CACHE_TTL, function (): int {
            $connection = config('tenancy.database.central_connection', config('database.default'));
            return Subscription::on($connection)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->distinct('tenant_id')
                ->count('tenant_id');
        });
    }

    /**
     * Churn rate: canceled in last 30 days / (active at start + new in period). Simplified: canceled last 30d / active now.
     */
    public function churnRateLast30Days(): float
    {
        $key = 'billing_analytics:churn_30';
        return (float) Cache::remember($key, self::CACHE_TTL, function (): float {
            $connection = config('tenancy.database.central_connection', config('database.default'));
            $canceled = Subscription::on($connection)
                ->where('status', Subscription::STATUS_CANCELED)
                ->where('updated_at', '>=', now()->subDays(30))
                ->count();
            $active = Subscription::on($connection)->where('status', Subscription::STATUS_ACTIVE)->count();
            if ($active + $canceled === 0) {
                return 0.0;
            }
            return round($canceled / ($active + $canceled) * 100, 2);
        });
    }

    /**
     * @return array<int, array{plan_name: string, plan_code: string, count: int, revenue: float}>
     */
    public function planDistribution(): array
    {
        $key = 'billing_analytics:plan_distribution';
        return Cache::remember($key, self::CACHE_TTL, function (): array {
            $connection = config('tenancy.database.central_connection', config('database.default'));
            $rows = Subscription::on($connection)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->with('plan')
                ->get()
                ->groupBy('plan_id');
            $out = [];
            foreach ($rows as $planId => $subs) {
                $plan = $subs->first()?->plan;
                $out[] = [
                    'plan_name' => $plan->name ?? $planId,
                    'plan_code' => $plan->code ?? '',
                    'count' => $subs->count(),
                    'revenue' => $subs->sum(fn ($s) => (float) ($s->plan->price ?? 0)),
                ];
            }
            return $out;
        });
    }
}
