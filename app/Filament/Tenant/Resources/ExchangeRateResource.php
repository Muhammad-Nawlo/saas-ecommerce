<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Models\Currency\ExchangeRate;
use App\Models\Currency\Currency;
use App\Services\Currency\ExchangeRateService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        $baseCurrency = app(\App\Services\Currency\CurrencyService::class)->getTenantBaseCurrency();
        $baseId = $baseCurrency?->id;
        $currencies = Currency::where('is_active', true)->orderBy('code')->pluck('code', 'id')->toArray();
        return $schema
            ->schema([
                SchemaSection::make('Rate')
                    ->schema([
                        Forms\Components\Select::make('base_currency_id')
                            ->label('From (base)')
                            ->options($currencies)
                            ->default($baseId)
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('target_currency_id')
                            ->label('To (target)')
                            ->options($currencies)
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('rate')
                            ->numeric()
                            ->required()
                            ->minValue(0.00000001)
                            ->step(0.0001),
                        Forms\Components\DateTimePicker::make('effective_at')->required()->default(now()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('baseCurrency.code')
                    ->label('Base')
                    ->getStateUsing(fn (?ExchangeRate $record) => $record?->baseCurrency?->code ?? '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('targetCurrency.code')
                    ->label('Target')
                    ->getStateUsing(fn (?ExchangeRate $record) => $record?->targetCurrency?->code ?? '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate')->numeric(decimalPlaces: 6)->sortable(),
                Tables\Columns\TextColumn::make('source')->badge()->sortable(),
                Tables\Columns\TextColumn::make('effective_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('base_currency_id')
                    ->relationship('baseCurrency', 'code')
                    ->label('Base'),
                Tables\Filters\SelectFilter::make('target_currency_id')
                    ->relationship('targetCurrency', 'code')
                    ->label('Target'),
            ])
            ->recordActions([
                Action::make('setManual')
                    ->label('Set rate')
                    ->visible(fn (ExchangeRate $record): bool => $record->baseCurrency !== null && $record->targetCurrency !== null)
                    ->form([
                        Forms\Components\TextInput::make('rate')->numeric()->required()->minValue(0.00000001),
                        Forms\Components\DateTimePicker::make('effective_at')->default(now()),
                    ])
                    ->action(function (ExchangeRate $record, array $data): void {
                        if ($record->baseCurrency === null || $record->targetCurrency === null) {
                            return;
                        }
                        app(ExchangeRateService::class)->setManualRate(
                            $record->baseCurrency,
                            $record->targetCurrency,
                            (float) $data['rate'],
                            isset($data['effective_at']) ? \Carbon\Carbon::parse($data['effective_at']) : null,
                        );
                    }),
            ])
            ->defaultSort('effective_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\ExchangeRateResource\Pages\ListExchangeRates::route('/'),
            'create' => \App\Filament\Tenant\Resources\ExchangeRateResource\Pages\CreateExchangeRate::route('/create'),
        ];
    }

    public static function canCreate(): bool
    {
        return function_exists('tenant_feature') && (bool) tenant_feature('multi_currency');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return function_exists('tenant_feature') && (bool) tenant_feature('multi_currency');
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['baseCurrency', 'targetCurrency']);
        $tid = tenant('id');
        if ($tid !== null) {
            $base = app(\App\Services\Currency\CurrencyService::class)->getTenantBaseCurrency();
            if ($base !== null) {
                $q->where('base_currency_id', $base->id);
            }
        }
        return $q;
    }
}
