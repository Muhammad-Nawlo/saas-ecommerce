<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialOrderItem;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Snapshot Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('snapshot remains immutable after issue', function (): void {
    $order = FinancialOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'FO-SNAP-001',
        'subtotal_cents' => 2000,
        'tax_total_cents' => 100,
        'total_cents' => 2100,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
        'snapshot' => ['currency' => 'USD', 'subtotal_cents' => 2000, 'tax_total_cents' => 100, 'total_cents' => 2100, 'items' => []],
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->createFromOrder($order);
    $snapshotBefore = $invoice->snapshot;
    $service->issue($invoice);
    $invoice->refresh();
    expect($invoice->snapshot)->toHaveKeys(['subtotal_cents', 'tax_total_cents', 'total_cents']);
    expect($invoice->snapshot['total_cents'])->toBe(2100);
})->group('invoice');
