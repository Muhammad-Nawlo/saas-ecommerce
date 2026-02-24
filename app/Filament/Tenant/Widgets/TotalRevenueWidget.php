<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalRevenueWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return [Stat::make('Total revenue', '$0')->description('All time')];
        }
        $total = OrderModel::forTenant((string) $tenantId)
            ->whereIn('status', ['paid', 'shipped'])
            ->sum('total_amount');
        $totalDollars = $total / 100;

        return [
            Stat::make('Total revenue', '$' . number_format($totalDollars, 2))
                ->description('Paid & shipped orders')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
