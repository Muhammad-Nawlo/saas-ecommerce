<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation;

use App\Models\Inventory\InventoryLocation;
use App\Services\Inventory\InventoryLocationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryLocationResource extends Resource
{
    protected static ?string $model = InventoryLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Location';

    protected static ?string $pluralModelLabel = 'Locations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('code')->required()->maxLength(64)->disabled(fn (?InventoryLocation $r) => $r !== null),
                        Forms\Components\Select::make('type')
                            ->options([
                                InventoryLocation::TYPE_WAREHOUSE => 'Warehouse',
                                InventoryLocation::TYPE_RETAIL_STORE => 'Retail store',
                                InventoryLocation::TYPE_FULFILLMENT_CENTER => 'Fulfillment center',
                            ])
                            ->required()
                            ->default(InventoryLocation::TYPE_WAREHOUSE),
                        Forms\Components\KeyValue::make('address')->nullable(),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (InventoryLocation $r) => $r->is_active)
                    ->action(fn (InventoryLocation $r) => app(InventoryLocationService::class)->deactivate($r))
                    ->requiresConfirmation(),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        $tid = tenant('id');
        if ($tid === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->forTenant((string) $tid);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource\Pages\ListInventoryLocations::route('/'),
            'create' => \App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource\Pages\CreateInventoryLocation::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource\Pages\EditInventoryLocation::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $tid = tenant('id');
        return $tid !== null && app(InventoryLocationService::class)->canCreateMoreLocations((string) $tid);
    }
}
