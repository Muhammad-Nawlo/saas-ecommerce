<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\InvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount_cents')->formatStateUsing(fn ($s) => number_format($s / 100, 2)),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('paid_at')->dateTime(),
            ])
            ->headerActions([])
            ->recordActions([]);
    }
}
