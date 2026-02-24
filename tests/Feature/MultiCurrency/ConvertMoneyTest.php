<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\ExchangeRate;
use App\Models\Currency\TenantCurrencySetting;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\ExchangeRateService;
use App\ValueObjects\Money;
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

test('convert money correctly using current rate', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $usd = Currency::where('code', 'USD')->first();
    $money = Money::fromCents(10000, 'USD'); // 100.00 USD
    $conversion = app(CurrencyConversionService::class);
    $converted = $conversion->convert($money, $eur);
    expect($converted->currency)->toBe('EUR');
    expect($converted->amount)->toBe(9200); // 100 * 0.92 = 92.00 EUR
})->group('multi_currency');

test('same currency conversion returns same amount', function (): void {
    $usd = Currency::where('code', 'USD')->first();
    $money = Money::fromCents(10000, 'USD');
    $converted = app(CurrencyConversionService::class)->convert($money, $usd);
    expect($converted->amount)->toBe(10000);
    expect($converted->currency)->toBe('USD');
})->group('multi_currency');
