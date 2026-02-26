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
    $this->tenant = Tenant::create(['name' => 'Lock Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('invoice locked after issuance', function (): void {
    $order = FinancialOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'FO-LOCK-001',
        'subtotal_cents' => 5000,
        'tax_total_cents' => 0,
        'total_cents' => 5000,
        'currency' => 'USD',
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
        'snapshot' => ['currency' => 'USD', 'subtotal_cents' => 5000, 'tax_total_cents' => 0, 'total_cents' => 5000, 'items' => []],
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->createFromOrder($order);
    $service->issue($invoice);
    $invoice->refresh();
    expect($invoice->locked_at)->not->toBeNull();
    expect($invoice->issued_at)->not->toBeNull();
    expect($invoice->status)->toBe(Invoice::STATUS_ISSUED);
    expect($invoice->isLocked())->toBeTrue();
})->group('invoice');
