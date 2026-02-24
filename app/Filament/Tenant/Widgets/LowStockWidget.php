<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Modules\Inventory\Infrastructure\Persistence\StockItemModel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return [Stat::make('Low stock', 0)->description('Products')];
        }
        $count = StockItemModel::forTenant((string) $tenantId)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->where('low_stock_threshold', '>', 0)
            ->count();

        return [
            Stat::make('Low stock', $count)
                ->description('Products at or below threshold')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
