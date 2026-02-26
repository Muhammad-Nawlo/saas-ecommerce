<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Financial;

use App\Models\Financial\FinancialOrder;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Financial orders. Immutable when locked. Subtotal/tax/total in cents.
 */
class FinancialOrderResource extends Resource
{
    protected static ?string $model = FinancialOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Financial';

    protected static ?string $modelLabel = 'Financial Order';

    protected static ?string $pluralModelLabel = 'Financial Orders';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SchemaSection::make('Order')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')->required()->maxLength(255),
                        Forms\Components\TextInput::make('property_id')->maxLength(36),
                        Forms\Components\Select::make('currency')
                            ->options(['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP'])
                            ->required()
                            ->default('USD'),
                        Forms\Components\Select::make('status')
                            ->options([
                                FinancialOrder::STATUS_DRAFT => 'Draft',
                                FinancialOrder::STATUS_PENDING => 'Pending',
                                FinancialOrder::STATUS_PAID => 'Paid',
                                FinancialOrder::STATUS_FAILED => 'Failed',
                                FinancialOrder::STATUS_REFUNDED => 'Refunded',
                            ])
                            ->required()
                            ->disabled(fn (?FinancialOrder $record) => $record?->isLocked()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subtotal_cents')
                    ->label('Subtotal')
                    ->formatStateUsing(fn (int $state, FinancialOrder $r) => number_format($state / 100, 2) . ' ' . $r->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_total_cents')
                    ->label('Tax')
                    ->formatStateUsing(fn (int $state, FinancialOrder $r) => number_format($state / 100, 2) . ' ' . $r->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state, FinancialOrder $r) => number_format($state / 100, 2) . ' ' . $r->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'paid' => 'success',
                    'pending' => 'warning',
                    'draft' => 'gray',
                    'refunded' => 'info',
                    'failed' => 'danger',
                    default => 'gray',
                })->sortable(),
                Tables\Columns\TextColumn::make('locked_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        FinancialOrder::STATUS_DRAFT => 'Draft',
                        FinancialOrder::STATUS_PENDING => 'Pending',
                        FinancialOrder::STATUS_PAID => 'Paid',
                        FinancialOrder::STATUS_FAILED => 'Failed',
                        FinancialOrder::STATUS_REFUNDED => 'Refunded',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (FinancialOrder $record) => !$record->isLocked()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages\ListFinancialOrders::route('/'),
            'create' => \App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages\CreateFinancialOrder::route('/create'),
            'view' => \App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages\ViewFinancialOrder::route('/{record}'),
            'edit' => \App\Filament\Tenant\Resources\Financial\FinancialOrderResource\Pages\EditFinancialOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->forTenant((string) $tenantId)->with('items');
    }

    public static function canEdit($record): bool
    {
        return $record instanceof FinancialOrder && !$record->isLocked();
    }
}
