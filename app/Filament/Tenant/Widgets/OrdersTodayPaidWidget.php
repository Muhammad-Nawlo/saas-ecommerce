<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Services\Reporting\ConversionReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdersTodayPaidWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = (string) tenant('id');
        $conversionReport = app(ConversionReportService::class);
        $count = $conversionReport->ordersToday($tenantId);

        return [
            Stat::make('Orders today', $count)
                ->description('Paid today')
                ->icon('heroicon-o-shopping-bag'),
        ];
    }
}
