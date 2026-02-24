<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial;

use App\Models\Financial\FinancialTransaction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinancialTransactionResource extends Resource
{
    protected static ?string $model = FinancialTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Financial';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->limit(8)->copyable(),
                Tables\Columns\TextColumn::make('order.order_number')->label('Order')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->color(fn (string $s) => match ($s) {
                    'credit' => 'success',
                    'debit' => 'warning',
                    'refund' => 'info',
                    default => 'gray',
                })->sortable(),
                Tables\Columns\TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $s, FinancialTransaction $r) => number_format($s / 100, 2) . ' ' . $r->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'completed' => 'success',
                    'pending' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                })->sortable(),
                Tables\Columns\TextColumn::make('provider_reference')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        FinancialTransaction::TYPE_DEBIT => 'Debit',
                        FinancialTransaction::TYPE_CREDIT => 'Credit',
                        FinancialTransaction::TYPE_REFUND => 'Refund',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        FinancialTransaction::STATUS_PENDING => 'Pending',
                        FinancialTransaction::STATUS_COMPLETED => 'Completed',
                        FinancialTransaction::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\Financial\FinancialTransactionResource\Pages\ListFinancialTransactions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->where(function (Builder $q) use ($tenantId): void {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
