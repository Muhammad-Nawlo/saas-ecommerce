<?php

declare(strict_types=1);

use App\Events\Financial\OrderPaid;
use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('dispatching OrderPaid twice does not create duplicate invoice', function (): void {
    Config::set('invoicing.auto_generate_invoice_on_payment', true);

    $tenant = Tenant::create(['name' => 'Idempotent Job Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FIN-IDEM-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
        'paid_at' => now(),
        'snapshot' => ['subtotal_cents' => 10000, 'total_cents' => 10000, 'currency' => 'USD'],
    ]);

    event(new OrderPaid($order, 'ref_1'));
    event(new OrderPaid($order, 'ref_1'));

    $count = Invoice::where('order_id', $order->id)->count();
    expect($count)->toBe(1);
})->group('idempotent', 'financial');
