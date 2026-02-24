<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Models\Inventory\InventoryLocationStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class OutOfStockProductsWidget extends BaseWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Out of stock products';

    public function table(Table $table): Table
    {
        $tid = tenant('id');
        if ($tid === null) {
            return $table->query(InventoryLocationStock::query()->whereRaw('1 = 0'));
        }
        $locationIds = \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->pluck('id');
        $productIdsWithStock = InventoryLocationStock::query()
            ->whereIn('location_id', $locationIds)
            ->selectRaw('product_id, SUM(quantity - reserved_quantity) as available')
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity - reserved_quantity) > 0')
            ->pluck('product_id');
        $allProductIds = \App\Modules\Catalog\Infrastructure\Persistence\ProductModel::forTenant((string) $tid)->pluck('id');
        $outOfStockIds = $allProductIds->diff($productIdsWithStock);

        return $table
            ->query(
                \App\Modules\Catalog\Infrastructure\Persistence\ProductModel::query()->whereIn('id', $outOfStockIds)
            )
            ->columns([
                TextColumn::make('name')->label('Product'),
                TextColumn::make('slug'),
            ])
            ->paginated([10, 25]);
    }
}
