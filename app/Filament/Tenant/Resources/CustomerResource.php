<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Modules\Orders\Infrastructure\Persistence\CustomerSummaryModel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Customer resource (read-only). Lists distinct customers from orders. Tenant context only. */
class CustomerResource extends Resource
{
    protected static ?string $model = CustomerSummaryModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->label('Customer email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('order_count')->label('Orders')->sortable(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total spent')
                    ->getStateUsing(fn (CustomerSummaryModel $r) => '$' . number_format($r->total_spent / 100, 2))
                    ->sortable(query: fn (Builder $q, string $dir) => $q->orderBy('total_spent', $dir)),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('order_count', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\CustomerResource\Pages\ListCustomers::route('/'),
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

    public static function canCreate(): bool
    {
        return false;
    }
}
