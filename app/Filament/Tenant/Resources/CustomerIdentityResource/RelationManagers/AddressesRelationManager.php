<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CustomerIdentityResource\RelationManagers;

use App\Models\Customer\CustomerAddress;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Addresses';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        CustomerAddress::TYPE_BILLING => 'Billing',
                        CustomerAddress::TYPE_SHIPPING => 'Shipping',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('line1')->required()->maxLength(255),
                Forms\Components\TextInput::make('line2')->maxLength(255),
                Forms\Components\TextInput::make('city')->required()->maxLength(255),
                Forms\Components\TextInput::make('state')->maxLength(100),
                Forms\Components\TextInput::make('postal_code')->required()->maxLength(20),
                Forms\Components\TextInput::make('country_code')->required()->length(2)->maxLength(2),
                Forms\Components\Toggle::make('is_default'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('line1'),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\TextColumn::make('postal_code'),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
