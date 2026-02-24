<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation;

use App\Models\Inventory\InventoryLocationStock;
use App\Services\Inventory\InventoryStockAdjustmentService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryLocationStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Location stock';

    protected static ?string $pluralModelLabel = 'Location stock';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $q): Builder {
                $tid = tenant('id');
                if ($tid === null) {
                    return $q->whereRaw('1 = 0');
                }
                $locationIds = \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->pluck('id');
                return $q->whereIn('location_id', $locationIds)->with(['product', 'location']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('location.name')->label('Location')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')->label('Reserved')->sortable(),
                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->getStateUsing(fn (InventoryLocationStock $r) => $r->availableQuantity())
                    ->sortable(query: fn ($q, string $dir) => $q->orderByRaw("(quantity - reserved_quantity) {$dir}")),
                Tables\Columns\TextColumn::make('low_stock_threshold')->label('Low at')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('is_low')
                    ->label('Low')
                    ->getStateUsing(fn (InventoryLocationStock $r) => $r->isLowStock())
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->label('Location'),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust')
                    ->label('Adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        Forms\Components\TextInput::make('delta')
                            ->label('Adjustment (+ or -)')
                            ->numeric()
                            ->required()
                            ->helperText('Positive to add, negative to subtract'),
                        Forms\Components\Textarea::make('reason')->label('Reason')->required()->default('Manual adjustment'),
                    ])
                    ->action(function (InventoryLocationStock $record, array $data): void {
                        app(InventoryStockAdjustmentService::class)->adjust(
                            $record->product_id,
                            $record->location_id,
                            (int) $data['delta'],
                            $data['reason'] ?? 'Manual adjustment',
                        );
                    }),
                Tables\Actions\Action::make('setThreshold')
                    ->label('Set low stock')
                    ->icon('heroicon-o-bell-alert')
                    ->form([
                        Forms\Components\TextInput::make('threshold')
                            ->label('Low stock threshold')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->default(fn (InventoryLocationStock $r) => $r->low_stock_threshold),
                    ])
                    ->action(function (InventoryLocationStock $record, array $data): void {
                        app(InventoryStockAdjustmentService::class)->setLowStockThreshold(
                            $record,
                            isset($data['threshold']) && $data['threshold'] !== '' ? (int) $data['threshold'] : null,
                        );
                    }),
            ])
            ->defaultSort('product_id');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\MultiLocation\InventoryStockResource\Pages\ListInventoryStocks::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
