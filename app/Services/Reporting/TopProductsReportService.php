<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Financial\FinancialOrderItem;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only top products report. Tenant isolated; uses financial order items.
 */
final readonly class TopProductsReportService
{
    private const CACHE_TTL = 300;

    /**
     * @return array<int, array{product_id: string, quantity: int, total_cents: int}>
     */
    public function topProducts(int $limit = 5, int $days = 30, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = tenant_cache_key("report:top_products:{$limit}:{$days}", $tenantId);
        return Cache::remember($key, self::CACHE_TTL, function () use ($tenantId, $limit, $days): array {
            $orderIds = \App\Models\Financial\FinancialOrder::where('tenant_id', $tenantId)
                ->where('status', \App\Models\Financial\FinancialOrder::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays($days))
                ->pluck('id');
            if ($orderIds->isEmpty()) {
                return [];
            }
            $items = FinancialOrderItem::whereIn('order_id', $orderIds)->get();
            $byProduct = [];
            foreach ($items as $item) {
                $meta = is_array($item->metadata) ? $item->metadata : [];
                $pid = $meta['product_id'] ?? 'unknown';
                if (!isset($byProduct[$pid])) {
                    $byProduct[$pid] = ['product_id' => $pid, 'quantity' => 0, 'total_cents' => 0];
                }
                $byProduct[$pid]['quantity'] += (int) $item->quantity;
                $byProduct[$pid]['total_cents'] += (int) $item->total_cents;
            }
            usort($byProduct, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);
            return array_slice(array_values($byProduct), 0, $limit);
        });
    }
}
