<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Models\Financial\FinancialOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RevenueByCurrencyWidget extends \Filament\Widgets\TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Revenue by currency';

    public function getTableRecordKey(Model|array $record): string
    {
        $key = is_array($record)
            ? ($record['currency'] ?? null)
            : $record->getAttribute('currency');
        return (string) $key;
    }

    public function table(Table $table): Table
    {
        $tid = tenant('id');
        $query = FinancialOrder::query()
            ->selectRaw('currency, SUM(total_cents) as total_cents, COUNT(*) as order_count')
            ->where('tenant_id', $tid)
            ->whereIn('status', [FinancialOrder::STATUS_PAID, FinancialOrder::STATUS_PENDING])
            ->groupBy('currency')
            ->orderBy('currency');
        return $table
            ->query($query)
            ->columns([
                TextColumn::make('currency')->label('Currency'),
                TextColumn::make('total_cents')->label('Total (minor units)'),
                TextColumn::make('order_count')->label('Orders'),
            ])
            ->defaultSort('currency')
            ->defaultKeySort(false)
            ->paginated(false);
    }
}
