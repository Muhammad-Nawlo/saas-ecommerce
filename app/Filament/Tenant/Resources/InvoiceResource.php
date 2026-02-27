<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoicePdfGenerator;
use App\Services\Invoice\InvoiceService;
use App\Modules\Shared\Domain\ValueObjects\Money;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Financial';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SchemaSection::make('Invoice')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                Invoice::STATUS_DRAFT => 'Draft',
                                Invoice::STATUS_ISSUED => 'Issued',
                                Invoice::STATUS_PAID => 'Paid',
                                Invoice::STATUS_PARTIALLY_PAID => 'Partially paid',
                                Invoice::STATUS_VOID => 'Void',
                                Invoice::STATUS_REFUNDED => 'Refunded',
                            ])
                            ->disabled(fn (?Invoice $record) => $record?->isLocked()),
                        Forms\Components\TextInput::make('currency')->disabled(),
                        Forms\Components\DatePicker::make('due_date')->nullable(),
                        Forms\Components\DateTimePicker::make('issued_at')->disabled(),
                        Forms\Components\DateTimePicker::make('paid_at')->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn (?Invoice $record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.email')->placeholder('—')->label('Customer'),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $s, Invoice $r) => number_format($s / 100, 2) . ' ' . $r->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'paid' => 'success',
                    'issued', 'partially_paid' => 'info',
                    'draft' => 'gray',
                    'void', 'refunded' => 'danger',
                    default => 'gray',
                })->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('issued_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    Invoice::STATUS_DRAFT => 'Draft',
                    Invoice::STATUS_ISSUED => 'Issued',
                    Invoice::STATUS_PAID => 'Paid',
                    Invoice::STATUS_PARTIALLY_PAID => 'Partially paid',
                    Invoice::STATUS_VOID => 'Void',
                    Invoice::STATUS_REFUNDED => 'Refunded',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Invoice $r) => !$r->isLocked()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Tenant\Resources\InvoiceResource\RelationManagers\ItemsRelationManager::class,
            \App\Filament\Tenant\Resources\InvoiceResource\RelationManagers\PaymentsRelationManager::class,
            \App\Filament\Tenant\Resources\InvoiceResource\RelationManagers\CreditNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\InvoiceResource\Pages\ListInvoices::route('/'),
            'view' => \App\Filament\Tenant\Resources\InvoiceResource\Pages\ViewInvoice::route('/{record}'),
            'edit' => \App\Filament\Tenant\Resources\InvoiceResource\Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->forTenant((string) $tenantId)->with(['customer', 'order']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
