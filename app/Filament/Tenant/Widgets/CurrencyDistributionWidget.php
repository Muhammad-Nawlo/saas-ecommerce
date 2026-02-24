<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Models\Financial\FinancialOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CurrencyDistributionWidget extends BaseWidget
{
    protected static ?int $sort = 21;

    protected function getStats(): array
    {
        $tid = tenant('id');
        if ($tid === null) {
            return [Stat::make('Revenue', '—')->description('By currency')];
        }
        $totals = FinancialOrder::query()
            ->where('tenant_id', $tid)
            ->whereIn('status', [FinancialOrder::STATUS_PAID, FinancialOrder::STATUS_PENDING])
            ->selectRaw('currency, SUM(total_cents) as total_cents')
            ->groupBy('currency')
            ->get();
        if ($totals->isEmpty()) {
            return [Stat::make('Revenue', '0')->description('No orders yet')];
        }
        $stats = [];
        foreach ($totals as $row) {
            $stats[] = Stat::make($row->currency, number_format($row->total_cents / 100, 2))
                ->description('Total revenue');
        }
        return $stats;
    }
}
