<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Widgets;

use App\Landlord\Services\BillingAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueByPlanWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $billingAnalytics = app(BillingAnalyticsService::class);
        $distribution = $billingAnalytics->planDistribution();
        if ($distribution === []) {
            return [
                Stat::make('Revenue by plan', '—')
                    ->description('No active subscriptions'),
            ];
        }
        $lines = [];
        foreach ($distribution as $row) {
            $lines[] = $row['plan_name'] . ': $' . number_format($row['revenue'], 2) . ' (' . $row['count'] . ')';
        }
        return [
            Stat::make('Revenue by plan', implode(' · ', $lines))
                ->description('MRR by plan')
                ->icon('heroicon-o-chart-pie'),
        ];
    }
}
