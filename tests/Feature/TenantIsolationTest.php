<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('financial orders are isolated per tenant', function (): void {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'data' => []]);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'data' => []]);

    $tenantA->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    $tenantB->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });

    tenancy()->initialize($tenantA);
    $orderA = FinancialOrder::create([
        'tenant_id' => $tenantA->id,
        'order_number' => 'FIN-A-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 1000,
        'tax_total_cents' => 0,
        'total_cents' => 1000,
        'status' => FinancialOrder::STATUS_DRAFT,
        'snapshot' => null,
    ]);

    tenancy()->initialize($tenantB);
    $orderB = FinancialOrder::create([
        'tenant_id' => $tenantB->id,
        'order_number' => 'FIN-B-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 2000,
        'tax_total_cents' => 0,
        'total_cents' => 2000,
        'status' => FinancialOrder::STATUS_DRAFT,
        'snapshot' => null,
    ]);

    tenancy()->initialize($tenantA);
    $foundA = FinancialOrder::where('id', $orderA->id)->first();
    $foundB = FinancialOrder::where('id', $orderB->id)->first();

    expect($foundA)->not->toBeNull();
    expect($foundA->total_cents)->toBe(1000);
    expect($foundB)->toBeNull();

    tenancy()->initialize($tenantB);
    $foundBInB = FinancialOrder::where('id', $orderB->id)->first();
    $foundAInB = FinancialOrder::where('id', $orderA->id)->first();

    expect($foundBInB)->not->toBeNull();
    expect($foundBInB->total_cents)->toBe(2000);
    expect($foundAInB)->toBeNull();
})->group('tenant_isolation');
