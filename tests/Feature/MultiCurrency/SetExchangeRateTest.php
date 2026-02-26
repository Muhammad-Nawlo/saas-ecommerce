<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\ExchangeRate;
use App\Models\Currency\TenantCurrencySetting;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'MC Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
        Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
    });
    tenancy()->initialize($this->tenant);
    $usd = Currency::where('code', 'USD')->first();
    TenantCurrencySetting::create([
        'tenant_id' => $this->tenant->id,
        'base_currency_id' => $usd->id,
        'allow_multi_currency' => false,
        'rounding_strategy' => 'half_up',
    ]);
});

test('set manual rate creates exchange rate', function (): void {
    $usd = Currency::where('code', 'USD')->first();
    $eur = Currency::where('code', 'EUR')->first();
    $service = app(ExchangeRateService::class);
    $rate = $service->setManualRate($usd, $eur, 0.92);
    expect($rate)->toBeInstanceOf(ExchangeRate::class);
    expect($rate->rate)->toBe(0.92);
    expect($rate->source)->toBe('manual');
});

test('get current rate returns set rate', function (): void {
    $usd = Currency::where('code', 'USD')->first();
    $eur = Currency::where('code', 'EUR')->first();
    $service = app(ExchangeRateService::class);
    $service->setManualRate($usd, $eur, 0.92);
    $current = $service->getCurrentRate($usd, $eur);
    expect($current)->not->toBeNull();
    expect($current->rate)->toBe(0.92);
})->group('multi_currency');
