<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Services\Reporting\TopProductsReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TopProductsWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenantId = (string) tenant('id');
        $topProductsReport = app(TopProductsReportService::class);
        $top = $topProductsReport->topProducts(5, 30, $tenantId);
        if ($top === []) {
            return [
                Stat::make('Top 5 products', '—')
                    ->description('Last 30 days (no data)'),
            ];
        }
        $lines = [];
        foreach (array_slice($top, 0, 5) as $i => $p) {
            $lines[] = ($i + 1) . '. ' . ($p['product_id'] !== 'unknown' ? $p['product_id'] : 'Product') . ': ' . $p['quantity'] . ' sold';
        }
        return [
            Stat::make('Top 5 products', implode(' · ', $lines))
                ->description('Last 30 days by quantity sold')
                ->icon('heroicon-o-star'),
        ];
    }
}
