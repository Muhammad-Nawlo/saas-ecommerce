<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CustomerIdentityResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('items'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #')->limit(8)->copyable(),
                Tables\Columns\TextColumn::make('customer_email'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state / 100, 2) : '—'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()->url(fn ($record) => \App\Filament\Tenant\Resources\OrderResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
