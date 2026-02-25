<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\PlanResource\RelationManagers;

use App\Landlord\Models\Feature;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Manages plan_features for a plan. Value: boolean => 1/0 or true/false, limit => integer or -1. */
class PlanFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'planFeatures';

    protected static ?string $title = 'Plan features';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('feature_id')
                    ->label('Feature')
                    ->options(
                        Feature::on($this->getOwnerRecord()->getConnectionName())->orderBy('code')->pluck('code', 'id')
                    )
                    ->required()
                    ->searchable()
                    ->live(),
                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->helperText('Boolean: 1/0 or true/false. Limit: integer or -1 for unlimited.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('feature.code')->label('Feature')->sortable(),
                Tables\Columns\TextColumn::make('feature.type')->badge()->label('Type'),
                Tables\Columns\TextColumn::make('value')->label('Value'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['plan_id'] = $this->getOwnerRecord()->getKey();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $q) => $q->with('feature'));
    }
}
