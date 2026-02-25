<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation;

use App\Models\Inventory\InventoryTransfer;
use App\Services\Inventory\InventoryTransferService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransferResource extends Resource
{
    protected static ?string $model = InventoryTransfer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-left';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        $tid = tenant('id');
        $locationOptions = $tid
            ? \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->active()->pluck('name', 'id')->toArray()
            : [];
        $productOptions = $tid
            ? \App\Modules\Catalog\Infrastructure\Persistence\ProductModel::forTenant((string) $tid)->orderBy('name')->pluck('name', 'id')->toArray()
            : [];

        return $form
            ->schema([
                Forms\Components\Section::make('Transfer')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options($productOptions)
                            ->required()
                            ->searchable()
                            ->disabled(fn (?InventoryTransfer $r) => $r !== null),
                        Forms\Components\Select::make('from_location_id')
                            ->label('From')
                            ->options($locationOptions)
                            ->required()
                            ->searchable()
                            ->disabled(fn (?InventoryTransfer $r) => $r !== null),
                        Forms\Components\Select::make('to_location_id')
                            ->label('To')
                            ->options($locationOptions)
                            ->required()
                            ->searchable()
                            ->disabled(fn (?InventoryTransfer $r) => $r !== null),
                        Forms\Components\TextInput::make('quantity')->numeric()->required()->minValue(1)->disabled(fn (?InventoryTransfer $r) => $r !== null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $q): Builder {
                $tid = tenant('id');
                if ($tid === null) {
                    return $q->whereRaw('1 = 0');
                }
                $locIds = \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->pluck('id');
                return $q->whereIn('from_location_id', $locIds)->with(['fromLocation', 'toLocation', 'product']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->sortable(),
                Tables\Columns\TextColumn::make('fromLocation.name')->label('From')->sortable(),
                Tables\Columns\TextColumn::make('toLocation.name')->label('To')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'completed' => 'success',
                    'pending' => 'warning',
                    'cancelled' => 'danger',
                    default => 'gray',
                })->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        InventoryTransfer::STATUS_PENDING => 'Pending',
                        InventoryTransfer::STATUS_COMPLETED => 'Completed',
                        InventoryTransfer::STATUS_CANCELLED => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->visible(fn (InventoryTransfer $r) => $r->status === InventoryTransfer::STATUS_PENDING)
                    ->action(fn (InventoryTransfer $r) => app(InventoryTransferService::class)->cancel($r))
                    ->requiresConfirmation()
                    ->color('danger'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\MultiLocation\InventoryTransferResource\Pages\ListInventoryTransfers::route('/'),
            'create' => \App\Filament\Tenant\Resources\MultiLocation\InventoryTransferResource\Pages\CreateInventoryTransfer::route('/create'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function canCreate(): bool
    {
        $tid = tenant('id');
        return $tid !== null && app(\App\Services\Inventory\InventoryLocationService::class)->canTransfer((string) $tid);
    }
}
