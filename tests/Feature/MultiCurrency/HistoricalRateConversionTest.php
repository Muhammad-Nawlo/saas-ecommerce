<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\ExchangeRate;
use App\Models\Currency\TenantCurrencySetting;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\ExchangeRateService;
use App\ValueObjects\Money;
use Carbon\Carbon;
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
    $rateService = app(ExchangeRateService::class);
    $rateService->setManualRate($usd, $eur, 0.90, Carbon::yesterday());
    $rateService->setManualRate($usd, $eur, 0.92, Carbon::now());
});

test('get rate at returns historical rate', function (): void {
    $usd = Currency::where('code', 'USD')->first();
    $eur = Currency::where('code', 'EUR')->first();
    $rateService = app(ExchangeRateService::class);
    $rateAt = $rateService->getRateAt($usd, $eur, Carbon::yesterday()->addHour());
    expect($rateAt)->not->toBeNull();
    expect($rateAt->rate)->toBe(0.90);
});

test('convert with historical date uses historical rate', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $money = Money::fromCents(10000, 'USD');
    $conversion = app(CurrencyConversionService::class);
    $converted = $conversion->convert($money, $eur, Carbon::yesterday()->addHour());
    expect($converted->currency)->toBe('EUR');
    expect($converted->amount)->toBe(9000); // 100 * 0.90
})->group('multi_currency');
