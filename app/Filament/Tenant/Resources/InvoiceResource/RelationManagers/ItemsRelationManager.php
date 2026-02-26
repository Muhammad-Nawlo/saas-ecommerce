<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\InvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Invoice items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('unit_price_cents')->formatStateUsing(fn ($s) => number_format($s / 100, 2)),
                Tables\Columns\TextColumn::make('subtotal_cents')->formatStateUsing(fn ($s) => number_format($s / 100, 2)),
                Tables\Columns\TextColumn::make('tax_cents')->formatStateUsing(fn ($s) => number_format($s / 100, 2)),
                Tables\Columns\TextColumn::make('total_cents')->formatStateUsing(fn ($s) => number_format($s / 100, 2)),
            ])
            ->headerActions([])
            ->recordActions([]);
    }
}
