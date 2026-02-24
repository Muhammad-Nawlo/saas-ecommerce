<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Models\Inventory\InventoryLocationStock;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalStockValueWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $tid = tenant('id');
        if ($tid === null) {
            return [Stat::make('Total stock value', '—')->description('Multi-location')];
        }
        $locationIds = \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->pluck('id');
        $totalMinor = InventoryLocationStock::query()
            ->whereIn('location_id', $locationIds)
            ->join('products', 'inventory_location_stocks.product_id', '=', 'products.id')
            ->selectRaw('SUM(inventory_location_stocks.quantity * products.price_minor_units) as total')
            ->value('total') ?? 0;
        $formatted = number_format($totalMinor / 100, 2);

        return [
            Stat::make('Total stock value', $formatted)
                ->description('Quantity × unit price across locations')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
