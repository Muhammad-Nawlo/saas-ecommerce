<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages;

use App\Filament\Tenant\Resources\Financial\FinancialOrderResource;
use App\Models\Financial\FinancialOrder;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class ViewFinancialOrder extends ViewRecord
{
    protected static string $resource = FinancialOrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Order')
                    ->schema([
                        TextEntry::make('order_number')->label('Order number'),
                        TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'draft' => 'gray',
                            'refunded' => 'info',
                            'failed' => 'danger',
                            default => 'gray',
                        }),
                        TextEntry::make('currency'),
                        TextEntry::make('subtotal_cents')
                            ->label('Subtotal')
                            ->formatStateUsing(fn (int $s, FinancialOrder $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                        TextEntry::make('tax_total_cents')
                            ->label('Tax')
                            ->formatStateUsing(fn (int $s, FinancialOrder $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                        TextEntry::make('total_cents')
                            ->label('Total')
                            ->formatStateUsing(fn (int $s, FinancialOrder $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                        TextEntry::make('locked_at')->dateTime()->placeholder('Not locked'),
                        TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Snapshot (immutable after lock)')
                    ->schema([
                        KeyValueEntry::make('snapshot')
                            ->label('')
                            ->visible(fn (?array $state) => !empty($state)),
                    ])
                    ->visible(fn (FinancialOrder $record) => $record->snapshot !== null),
            ]);
    }
}
