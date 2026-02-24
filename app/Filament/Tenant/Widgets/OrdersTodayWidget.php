<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdersTodayWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return [Stat::make('Orders today', 0)->description('Today')];
        }
        $count = OrderModel::forTenant((string) $tenantId)
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Orders today', $count)
                ->description('Created today')
                ->icon('heroicon-o-shopping-bag'),
        ];
    }
}
