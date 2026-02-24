<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Financial\FinancialOrder;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only conversion metrics. Tenant isolated.
 */
final readonly class ConversionReportService
{
    private const CACHE_TTL = 300;

    public function ordersToday(?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = "report:orders_today:{$tenantId}";
        return (int) Cache::remember($key, self::CACHE_TTL, function () use ($tenantId): int {
            return FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->whereDate('paid_at', today())
                ->count();
        });
    }

    public function conversionRateLastDays(int $days, ?string $tenantId = null): float
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = "report:conversion:{$tenantId}:{$days}";
        return (float) Cache::remember($key, self::CACHE_TTL, function () use ($tenantId, $days): float {
            $created = OrderModel::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subDays($days))
                ->count();
            if ($created === 0) {
                return 0.0;
            }
            $paid = FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays($days))
                ->count();
            return round($paid / $created * 100, 2);
        });
    }

    public function averageOrderValueLastDays(int $days, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = "report:aov:{$tenantId}:{$days}";
        return (int) Cache::remember($key, self::CACHE_TTL, function () use ($tenantId, $days): int {
            $count = FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays($days))
                ->count();
            if ($count === 0) {
                return 0;
            }
            $sum = (int) FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', FinancialOrder::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays($days))
                ->sum('total_cents');
            return (int) round($sum / $count);
        });
    }
}
