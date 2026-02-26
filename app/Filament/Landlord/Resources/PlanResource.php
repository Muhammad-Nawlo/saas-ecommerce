<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources;

use App\Filament\Landlord\Resources\PlanResource\RelationManagers\PlanFeaturesRelationManager;
use App\Landlord\Models\Plan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Plan resource. Landlord DB only. */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Plan details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0),
                        Forms\Components\Select::make('billing_interval')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('monthly')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('billing_interval')->badge()->sortable(),
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
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            PlanFeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Landlord\Resources\PlanResource\Pages\ListPlans::route('/'),
            'create' => \App\Filament\Landlord\Resources\PlanResource\Pages\CreatePlan::route('/create'),
            'edit' => \App\Filament\Landlord\Resources\PlanResource\Pages\EditPlan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('planFeatures.feature');
    }
}
