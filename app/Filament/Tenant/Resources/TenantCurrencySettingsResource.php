<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Models\Currency\TenantCurrencySetting;
use App\Models\Currency\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantCurrencySettingsResource extends Resource
{
    protected static ?string $model = TenantCurrencySetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 14;

    protected static ?string $modelLabel = 'Currency settings';

    protected static ?string $pluralModelLabel = 'Currency settings';

    public static function form(Form $form): Form
    {
        $currencies = Currency::where('is_active', true)->orderBy('code')->pluck('name', 'id')->toArray();
        return $form
            ->schema([
                Forms\Components\Section::make('Tenant currency')
                    ->schema([
                        Forms\Components\Select::make('base_currency_id')
                            ->label('Base currency')
                            ->options($currencies)
                            ->required()
                            ->searchable(),
                        Forms\Components\Toggle::make('allow_multi_currency')
                            ->label('Allow multiple selling currencies')
                            ->helperText('Requires multi_currency feature on plan.')
                            ->default(false),
                        Forms\Components\Select::make('rounding_strategy')
                            ->label('Rounding')
                            ->options([
                                TenantCurrencySetting::ROUNDING_HALF_UP => 'Half up (default)',
                                TenantCurrencySetting::ROUNDING_HALF_DOWN => 'Half down',
                                TenantCurrencySetting::ROUNDING_BANKERS => 'Bankers (round half to even)',
                            ])
                            ->required()
                            ->default(TenantCurrencySetting::ROUNDING_HALF_UP),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->where('tenant_id', tenant('id'))->with('baseCurrency'))
            ->columns([
                Tables\Columns\TextColumn::make('baseCurrency.name')->label('Base currency'),
                Tables\Columns\IconColumn::make('allow_multi_currency')->label('Multi-currency')->boolean(),
                Tables\Columns\TextColumn::make('rounding_strategy')->label('Rounding'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\TenantCurrencySettingsResource\Pages\ListTenantCurrencySettings::route('/'),
            'create' => \App\Filament\Tenant\Resources\TenantCurrencySettingsResource\Pages\CreateTenantCurrencySettings::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\TenantCurrencySettingsResource\Pages\EditTenantCurrencySettings::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $tid = tenant('id');
        if ($tid === null) {
            return false;
        }
        return !TenantCurrencySetting::where('tenant_id', $tid)->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $tid = tenant('id');
        if ($tid === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        return parent::getEloquentQuery()->where('tenant_id', $tid);
    }
}
