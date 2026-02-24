<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Services\Currency\CurrencyService;
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
    TenantCurrencySetting::create([
        'tenant_id' => $this->tenant->id,
        'base_currency_id' => $usd->id,
        'allow_multi_currency' => false,
        'rounding_strategy' => 'half_up',
    ]);
});

test('list enabled currencies returns only base when multi currency disabled', function (): void {
    $service = app(CurrencyService::class);
    $list = $service->listEnabledCurrencies();
    expect($list)->toHaveCount(1);
    expect($list->first()->code)->toBe('USD');
})->group('multi_currency');

test('enable currency throws when tenant allow_multi_currency is false', function (): void {
    $eur = Currency::where('code', 'EUR')->first();
    $service = app(CurrencyService::class);
    $service->enableCurrency($eur->id);
})->throws(\InvalidArgumentException::class)->group('multi_currency');
