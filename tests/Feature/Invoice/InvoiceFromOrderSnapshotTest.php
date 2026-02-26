<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Invoice Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('invoice created from order snapshot', function (): void {
    $order = FinancialOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'FO-INV-001',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 800,
        'total_cents' => 10800,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
        'snapshot' => [
            'currency' => 'USD',
            'subtotal_cents' => 10000,
            'tax_total_cents' => 800,
            'total_cents' => 10800,
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price_cents' => 10000, 'subtotal_cents' => 10000, 'tax_cents' => 800, 'total_cents' => 10800, 'metadata' => null],
            ],
        ],
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->createFromOrder($order);
    expect($invoice)->toBeInstanceOf(Invoice::class);
    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT);
    expect($invoice->order_id)->toBe($order->id);
    expect($invoice->subtotal_cents)->toBe(10000);
    expect($invoice->tax_total_cents)->toBe(800);
    expect($invoice->total_cents)->toBe(10800);
    expect($invoice->snapshot)->toBeArray();
    expect($invoice->items)->toHaveCount(1);
})->group('invoice');
