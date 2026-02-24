<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Inventory\Infrastructure\Persistence\StockItemModel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockProductsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low stock products';

    public function table(Table $table): Table
    {
        $tenantId = tenant('id');
        $query = $tenantId
            ? StockItemModel::forTenant((string) $tenantId)
                ->whereColumn('quantity', '<=', 'low_stock_threshold')
                ->where('low_stock_threshold', '>', 0)
                ->with('product')
            : StockItemModel::query()->whereRaw('1 = 0');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('quantity')->label('Quantity'),
                TextColumn::make('low_stock_threshold')->label('Threshold'),
            ])
            ->paginated([5, 10]);
    }
}
