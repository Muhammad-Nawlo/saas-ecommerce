<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources;

use App\Landlord\Models\Feature;
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

/** Feature resource. Landlord DB only. code must be unique. */
class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Feature details')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->options([
                                Feature::TYPE_LIMIT => 'Limit',
                                Feature::TYPE_BOOLEAN => 'Boolean',
                            ])
                            ->required()
                            ->default(Feature::TYPE_LIMIT),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Feature::TYPE_LIMIT => 'Limit',
                        Feature::TYPE_BOOLEAN => 'Boolean',
                    ]),
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
            ->defaultSort('code')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Landlord\Resources\FeatureResource\Pages\ListFeatures::route('/'),
            'create' => \App\Filament\Landlord\Resources\FeatureResource\Pages\CreateFeature::route('/create'),
            'edit' => \App\Filament\Landlord\Resources\FeatureResource\Pages\EditFeature::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
