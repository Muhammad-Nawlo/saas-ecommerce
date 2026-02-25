<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Modules\Catalog\Infrastructure\Persistence\CategoryModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Category resource. Tenant context only. */
class CategoryResource extends Resource
{
    protected static ?string $model = CategoryModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $tenantId = tenant('id');
        $parentOptions = $tenantId
            ? CategoryModel::forTenant((string) $tenantId)->orderBy('name')->pluck('name', 'id')->toArray()
            : [];

        return $schema
            ->schema([
                Forms\Components\Section::make('Category details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', $tenantId)),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent category')
                            ->options($parentOptions)
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\CategoryResource\Pages\ListCategories::route('/'),
            'create' => \App\Filament\Tenant\Resources\CategoryResource\Pages\CreateCategory::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\CategoryResource\Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->forTenant((string) $tenantId)->with('parent');
    }
}
