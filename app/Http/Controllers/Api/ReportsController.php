<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Reporting\ConversionReportService;
use App\Services\Reporting\RevenueReportService;
use App\Services\Reporting\TaxReportService;
use App\Services\Reporting\TopProductsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only report API. Tenant isolated; uses short TTL cache.
 */
final class ReportsController
{
    public function __construct(
        private RevenueReportService $revenueReport,
        private TaxReportService $taxReport,
        private TopProductsReportService $topProductsReport,
        private ConversionReportService $conversionReport,
    ) {
    }

    public function revenue(Request $request): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $days = min(90, max(1, (int) $request->get('days', 30)));
        return response()->json([
            'revenue_today_cents' => $this->revenueReport->revenueToday($tenantId),
            'revenue_last_days_cents' => $this->revenueReport->revenueLastDays($days, $tenantId),
            'period_days' => $days,
        ]);
    }

    public function tax(Request $request): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $days = min(90, max(1, (int) $request->get('days', 30)));
        return response()->json([
            'tax_collected_last_days_cents' => $this->taxReport->taxCollectedLastDays($days, $tenantId),
            'period_days' => $days,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $limit = min(20, max(1, (int) $request->get('limit', 5)));
        $days = min(90, max(1, (int) $request->get('days', 30)));
        return response()->json([
            'top_products' => $this->topProductsReport->topProducts($limit, $days, $tenantId),
            'period_days' => $days,
        ]);
    }

    public function conversion(Request $request): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $days = min(90, max(1, (int) $request->get('days', 30)));
        return response()->json([
            'orders_today' => $this->conversionReport->ordersToday($tenantId),
            'conversion_rate_percent' => $this->conversionReport->conversionRateLastDays($days, $tenantId),
            'average_order_value_cents' => $this->conversionReport->averageOrderValueLastDays($days, $tenantId),
            'period_days' => $days,
        ]);
    }
}
