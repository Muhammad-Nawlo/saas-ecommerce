<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Inventory\Infrastructure\Persistence\StockItemModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Inventory (stock) resource. Tenant context only. */
class InventoryResource extends Resource
{
    protected static ?string $model = StockItemModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $tenantId = tenant('id');
        $productOptions = $tenantId
            ? ProductModel::forTenant((string) $tenantId)->orderBy('name')->pluck('name', 'id')->toArray()
            : [];

        return $form
            ->schema([
                Forms\Components\Section::make('Stock')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options($productOptions)
                            ->required()
                            ->searchable()
                            ->disabled(fn (?string $operation) => $operation === 'edit'),
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('low_stock_threshold')
                            ->label('Low stock threshold')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Alert when quantity falls at or below this value.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')->label('Reserved')->sortable(),
                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->getStateUsing(fn (StockItemModel $r) => max(0, $r->quantity - $r->reserved_quantity)),
                Tables\Columns\TextColumn::make('low_stock_threshold')->label('Low at')->sortable(),
                Tables\Columns\IconColumn::make('is_low')
                    ->label('Low stock')
                    ->getStateUsing(fn (StockItemModel $r) => $r->low_stock_threshold > 0 && ($r->quantity - $r->reserved_quantity) <= $r->low_stock_threshold)
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('quantity')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\InventoryResource\Pages\ListInventory::route('/'),
            'create' => \App\Filament\Tenant\Resources\InventoryResource\Pages\CreateInventory::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\InventoryResource\Pages\EditInventory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->forTenant((string) $tenantId);
    }

    public static function getModelLabel(): string
    {
        return 'Stock';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Inventory';
    }
}
