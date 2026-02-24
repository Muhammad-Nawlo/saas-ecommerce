<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Services\Reporting\ConversionReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageOrderValueWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = (string) tenant('id');
        $conversionReport = app(ConversionReportService::class);
        $cents = $conversionReport->averageOrderValueLastDays(30, $tenantId);
        $formatted = number_format($cents / 100, 2);

        return [
            Stat::make('Average order value', $formatted)
                ->description('Last 30 days')
                ->icon('heroicon-o-calculator'),
        ];
    }
}
