<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Order resource. Tenant context only. Read-only except status and internal notes. */
class OrderResource extends Resource
{
    protected static ?string $model = OrderModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order')
                    ->schema([
                        Forms\Components\TextInput::make('id')->disabled()->label('Order ID'),
                        Forms\Components\TextInput::make('customer_email')->disabled()->label('Customer'),
                        Forms\Components\Placeholder::make('total_placeholder')
                            ->label('Total')
                            ->content(fn ($record) => $record ? '$' . number_format($record->total_amount / 100, 2) : ''),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'paid' => 'Paid',
                                'shipped' => 'Shipped',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('items'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #')->limit(8)->copyable()->sortable(),
                Tables\Columns\TextColumn::make('customer_email')->searchable()->sortable()->label('Customer'),
                Tables\Columns\TextColumn::make('total_display')
                    ->label('Total')
                    ->getStateUsing(fn (OrderModel $r) => '$' . number_format($r->total_amount / 100, 2))
                    ->sortable(query: fn (Builder $q, string $dir) => $q->orderBy('total_amount', $dir)),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'paid', 'shipped' => 'success',
                    'confirmed' => 'info',
                    'pending' => 'warning',
                    'cancelled' => 'danger',
                    default => 'gray',
                })->sortable()->label('Fulfillment'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->getStateUsing(fn (OrderModel $r) => in_array($r->status, ['paid', 'shipped'], true) ? 'Paid' : 'Unpaid')
                    ->badge()
                    ->color(fn (OrderModel $r) => in_array($r->status, ['paid', 'shipped'], true) ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label('Date'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'paid' => 'Paid',
                        'shipped' => 'Shipped',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        if (!empty($data['from'])) {
                            $q->whereDate('created_at', '>=', $data['from']);
                        }
                        if (!empty($data['until'])) {
                            $q->whereDate('created_at', '<=', $data['until']);
                        }
                        return $q;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\OrderResource\Pages\ListOrders::route('/'),
            'edit' => \App\Filament\Tenant\Resources\OrderResource\Pages\EditOrder::route('/{record}/edit'),
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
