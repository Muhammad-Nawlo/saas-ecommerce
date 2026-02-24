<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Models\Inventory\InventoryLocationStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockLocationsWidget extends BaseWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low stock by location';

    public function table(Table $table): Table
    {
        $tid = tenant('id');
        $locationIds = $tid ? \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->pluck('id') : collect();

        return $table
            ->query(
                InventoryLocationStock::query()
                    ->whereIn('location_id', $locationIds)
                    ->whereNotNull('low_stock_threshold')
                    ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold')
                    ->with(['product', 'location'])
            )
            ->columns([
                TextColumn::make('product.name')->label('Product'),
                TextColumn::make('location.name')->label('Location'),
                TextColumn::make('quantity'),
                TextColumn::make('reserved_quantity')->label('Reserved'),
                TextColumn::make('low_stock_threshold')->label('Threshold'),
            ])
            ->paginated([10, 25]);
    }
}
