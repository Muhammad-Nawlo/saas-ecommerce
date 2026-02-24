<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Services\Reporting\RevenueReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueLast30Widget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = (string) tenant('id');
        $revenueReport = app(RevenueReportService::class);
        $cents = $revenueReport->revenueLastDays(30, $tenantId);
        $formatted = number_format($cents / 100, 2);

        return [
            Stat::make('Revenue (30 days)', $formatted)
                ->description('Last 30 days')
                ->icon('heroicon-o-chart-bar'),
        ];
    }
}
