<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Services\Reporting\ConversionReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConversionRateWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = (string) tenant('id');
        $conversionReport = app(ConversionReportService::class);
        $rate = $conversionReport->conversionRateLastDays(30, $tenantId);

        return [
            Stat::make('Conversion rate', $rate . '%')
                ->description('Last 30 days')
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }
}
