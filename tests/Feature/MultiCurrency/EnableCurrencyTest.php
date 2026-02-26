<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Models\Currency\TenantEnabledCurrency;
use App\Services\Currency\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'MC Test', 'data' => ['features' => ['multi_currency' => true]]]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
        Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
    });
    tenancy()->initialize($this->tenant);
    $base = Currency::where('code', 'USD')->first();
    TenantCurrencySetting::create([
        'tenant_id' => $this->tenant->id,
        'base_currency_id' => $base->id,
        'allow_multi_currency' => true,
        'rounding_strategy' => 'half_up',
    ]);
});

test('enable currency adds to tenant enabled list', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $service = app(CurrencyService::class);
    $service->enableCurrency($eur->id);
    expect(TenantEnabledCurrency::where('tenant_id', $this->tenant->id)->where('currency_id', $eur->id)->exists())->toBeTrue();
})->group('multi_currency');

test('disable currency removes from tenant enabled list', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $service = app(CurrencyService::class);
    $service->enableCurrency($eur->id);
    $service->disableCurrency($eur->id);
    expect(TenantEnabledCurrency::where('tenant_id', $this->tenant->id)->where('currency_id', $eur->id)->exists())->toBeFalse();
})->group('multi_currency');
