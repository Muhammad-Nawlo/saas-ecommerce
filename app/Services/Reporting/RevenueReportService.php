<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Financial\FinancialOrder;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only revenue reporting. Tenant isolated; never mutates data.
 */
final readonly class RevenueReportService
{
    private const CACHE_TTL = 300; // 5 min

    public function revenueToday(?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = tenant_cache_key('report:revenue_today', $tenantId);
        return (int) Cache::remember($key, self::CACHE_TTL, function () use ($tenantId): int {
            return (int) FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->whereDate('paid_at', today())
                ->sum('total_cents');
        });
    }

    public function revenueLastDays(int $days, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = tenant_cache_key("report:revenue_last_{$days}d", $tenantId);
        return (int) Cache::remember($key, self::CACHE_TTL, function () use ($tenantId, $days): int {
            return (int) FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays($days))
                ->sum('total_cents');
        });
    }

    /**
     * @return array{period: string, total_cents: int, currency: string}[]
     */
    public function revenueByPeriod(string $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = FinancialOrder::where('tenant_id', $tenantId)
            ->where('status', FinancialOrder::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as period, SUM(total_cents) as total_cents, currency')
            ->groupBy('period', 'currency')
            ->orderBy('period')
            ->get();
        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'total_cents' => (int) $r->total_cents,
            'currency' => $r->currency,
        ])->all();
    }
}
