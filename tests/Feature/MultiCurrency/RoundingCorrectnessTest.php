<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\ExchangeRate;
use App\Models\Currency\TenantCurrencySetting;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\ExchangeRateService;
use App\Modules\Shared\Domain\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'MC Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
        Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
    });
    tenancy()->initialize($this->tenant);
    $usd = Currency::where('code', 'USD')->first();
    $eur = Currency::where('code', 'EUR')->first();
    TenantCurrencySetting::create([
        'tenant_id' => $this->tenant->id,
        'base_currency_id' => $usd->id,
        'allow_multi_currency' => true,
        'rounding_strategy' => 'half_up',
    ]);
    app(ExchangeRateService::class)->setManualRate($usd, $eur, 0.92);
});

test('half_up rounding rounds correctly', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $money = Money::fromMinorUnits(10001, 'USD'); // 100.01 USD -> 92.0092 EUR -> 9201 cents
    $converted = app(CurrencyConversionService::class)->convert($money, $eur);
    expect($converted->getCurrency())->toBe('EUR');
    expect($converted->getMinorUnits())->toBe(9201);
})->group('multi_currency');

test('conversion service returns correct amount for rate', function (): void {
    $money = Money::fromMinorUnits(10000, 'USD');
    $converted = app(CurrencyConversionService::class)->convert($money, Currency::where('code', 'EUR')->first());
    expect($converted->getCurrency())->toBe('EUR');
    expect($converted->getMinorUnits())->toBe(9200);
})->group('multi_currency');
