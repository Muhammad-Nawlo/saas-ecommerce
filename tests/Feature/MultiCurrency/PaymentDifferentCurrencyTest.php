<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Services\Currency\CurrencyConversionService;
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
    app(\App\Services\Currency\ExchangeRateService::class)->setManualRate($usd, $eur, 0.92);
});

test('convert with snapshot returns converted amount and rate snapshot', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $usd = Currency::where('code', 'USD')->first();
    $money = Money::fromCents(10000, 'USD');
    $conversion = app(CurrencyConversionService::class);
    $result = $conversion->convertWithSnapshot($money, $eur);
    expect($result['converted']->currency)->toBe('EUR');
    expect($result['converted']->amount)->toBe(9200);
    expect($result['rate_snapshot'])->toBeArray();
    expect($result['rate_snapshot']['base_code'] ?? null)->toBe('USD');
    expect($result['rate_snapshot']['target_code'] ?? null)->toBe('EUR');
})->group('multi_currency');
