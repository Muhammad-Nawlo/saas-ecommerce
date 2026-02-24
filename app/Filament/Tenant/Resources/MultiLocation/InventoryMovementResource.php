<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation;

use App\Models\Inventory\InventoryMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $q): Builder {
                $tid = tenant('id');
                if ($tid === null) {
                    return $q->whereRaw('1 = 0');
                }
                $locIds = \App\Models\Inventory\InventoryLocation::forTenant((string) $tid)->pluck('id');
                return $q->whereIn('location_id', $locIds)->with('location');
            })
            ->columns([
                Tables\Columns\TextColumn::make('product_id')->label('Product ID')->searchable(),
                Tables\Columns\TextColumn::make('location.name')->label('Location')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('reference_type')->placeholder('—'),
                Tables\Columns\TextColumn::make('reference_id')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        InventoryMovement::TYPE_INCREASE => 'Increase',
                        InventoryMovement::TYPE_DECREASE => 'Decrease',
                        InventoryMovement::TYPE_RESERVE => 'Reserve',
                        InventoryMovement::TYPE_RELEASE => 'Release',
                        InventoryMovement::TYPE_TRANSFER_OUT => 'Transfer out',
                        InventoryMovement::TYPE_TRANSFER_IN => 'Transfer in',
                        InventoryMovement::TYPE_ADJUSTMENT => 'Adjustment',
                    ]),
                Tables\Filters\SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->label('Location'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $q, array $data): Builder {
                        if (!empty($data['from'])) {
                            $q->whereDate('created_at', '>=', $data['from']);
                        }
                        if (!empty($data['until'])) {
                            $q->whereDate('created_at', '<=', $data['until']);
                        }
                        return $q;
                    }),
            ])
            ->actions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\MultiLocation\InventoryMovementResource\Pages\ListInventoryMovements::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
