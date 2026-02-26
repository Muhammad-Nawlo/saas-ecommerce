<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Shared\Domain\Exceptions\FeatureNotEnabledException;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Product resource. Tenant context only. Enforces products_limit on create. */
class ProductResource extends Resource
{
    protected static ?string $model = ProductModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SchemaSection::make('Product details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug((string) $state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', tenant('id'))),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('price_minor_units')
                            ->label('Price (USD)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->dehydrateStateUsing(fn ($state) => (int) round((float) $state * 100))
                            ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) $state / 100 : 0),
                        Forms\Components\Hidden::make('currency')->default('USD'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status: Active')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('price_display')
                    ->label('Price')
                    ->getStateUsing(fn (ProductModel $r) => '$' . number_format($r->price_minor_units / 100, 2))
                    ->sortable(query: function (Builder $q, string $direction) {
                        return $q->orderBy('price_minor_units', $direction);
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\ProductResource\Pages\ListProducts::route('/'),
            'create' => \App\Filament\Tenant\Resources\ProductResource\Pages\CreateProduct::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\ProductResource\Pages\EditProduct::route('/{record}/edit'),
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
        return 'Product';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Products';
    }
}
