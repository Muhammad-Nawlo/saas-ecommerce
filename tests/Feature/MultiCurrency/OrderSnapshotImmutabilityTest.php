<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\ExchangeRate;
use App\Models\Currency\TenantCurrencySetting;
use App\Models\Financial\FinancialOrder;
use App\Services\Currency\ExchangeRateService;
use App\Services\Currency\OrderCurrencySnapshotService;
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
    $eur = Currency::where('code', 'EUR')->first();
    TenantCurrencySetting::create([
        'tenant_id' => $this->tenant->id,
        'base_currency_id' => $usd->id,
        'allow_multi_currency' => true,
        'rounding_strategy' => 'half_up',
    ]);
    app(ExchangeRateService::class)->setManualRate($usd, $eur, 0.92);
});

test('order snapshot stores base and display amounts and rate snapshot', function (): void {
    $order = FinancialOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'ORD-SNAP-001',
        'currency' => 'EUR',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 1000,
        'total_cents' => 11000,
        'status' => FinancialOrder::STATUS_PENDING,
    ]);
    app(OrderCurrencySnapshotService::class)->fillSnapshot($order);
    $order->refresh();
    expect($order->base_currency)->toBe('USD');
    expect($order->display_currency)->toBe('EUR');
    expect($order->exchange_rate_snapshot)->toBeArray();
    expect($order->exchange_rate_snapshot['rate'] ?? null)->toBe(0.92);
    expect($order->total_base_cents)->not->toBeNull();
})->group('multi_currency');

test('order snapshot is idempotent', function (): void {
    $order = FinancialOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'ORD-SNAP-002',
        'currency' => 'USD',
        'subtotal_cents' => 5000,
        'tax_total_cents' => 500,
        'total_cents' => 5500,
        'status' => FinancialOrder::STATUS_PENDING,
    ]);
    $service = app(OrderCurrencySnapshotService::class);
    $service->fillSnapshot($order);
    $order->refresh();
    $snapshot = $order->exchange_rate_snapshot;
    $service->fillSnapshot($order);
    $order->refresh();
    expect($order->exchange_rate_snapshot)->toBe($snapshot);
})->group('multi_currency');
