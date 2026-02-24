<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Services\Reporting\RevenueReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueTodayWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = (string) tenant('id');
        $revenueReport = app(RevenueReportService::class);
        $cents = $revenueReport->revenueToday($tenantId);
        $formatted = number_format($cents / 100, 2);

        return [
            Stat::make('Revenue today', $formatted)
                ->description('Paid orders today')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
