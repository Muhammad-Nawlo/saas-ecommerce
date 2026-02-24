<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Financial\FinancialOrder;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only tax reporting. Tenant isolated.
 */
final readonly class TaxReportService
{
    private const CACHE_TTL = 300;

    public function taxCollectedLastDays(int $days, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = "report:tax_last_{$days}d:{$tenantId}";
        return (int) Cache::remember($key, self::CACHE_TTL, function () use ($tenantId, $days): int {
            return (int) FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays($days))
                ->sum('tax_total_cents');
        });
    }

    /**
     * @return array{period: string, tax_total_cents: int}[]
     */
    public function taxByPeriod(string $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = FinancialOrder::where('tenant_id', $tenantId)
            ->where('status', FinancialOrder::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as period, SUM(tax_total_cents) as tax_total_cents')
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'tax_total_cents' => (int) $r->tax_total_cents,
        ])->all();
    }
}
