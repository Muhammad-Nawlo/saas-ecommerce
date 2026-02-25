<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Models\Currency\Currency;
use App\Services\Currency\CurrencyService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Currency')
                    ->schema([
                        Forms\Components\TextInput::make('code')->required()->length(3)->uppercase()->disabled(fn (?Currency $r) => $r !== null),
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('symbol')->required()->maxLength(16),
                        Forms\Components\TextInput::make('decimal_places')->numeric()->minValue(0)->maxValue(4)->default(2),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('symbol'),
                Tables\Columns\TextColumn::make('decimal_places')->label('Decimals'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('enableForTenant')
                    ->label('Enable')
                    ->visible(fn (Currency $r) => static::canMultiCurrency() && !static::isEnabledForTenant($r))
                    ->action(fn (Currency $r) => app(CurrencyService::class)->enableCurrency($r->id))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('disableForTenant')
                    ->label('Disable')
                    ->visible(fn (Currency $r) => static::canMultiCurrency() && static::isEnabledForTenant($r))
                    ->action(fn (Currency $r) => app(CurrencyService::class)->disableCurrency($r->id))
                    ->requiresConfirmation()
                    ->color('warning'),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\CurrencyResource\Pages\ListCurrencies::route('/'),
            'edit' => \App\Filament\Tenant\Resources\CurrencyResource\Pages\EditCurrency::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canMultiCurrency();
    }

    public static function canMultiCurrency(): bool
    {
        return function_exists('tenant_feature') && (bool) tenant_feature('multi_currency');
    }

    private static function isEnabledForTenant(Currency $currency): bool
    {
        $tid = tenant('id');
        if ($tid === null) {
            return false;
        }
        return \App\Models\Currency\TenantEnabledCurrency::where('tenant_id', $tid)
            ->where('currency_id', $currency->id)
            ->exists();
    }
}
