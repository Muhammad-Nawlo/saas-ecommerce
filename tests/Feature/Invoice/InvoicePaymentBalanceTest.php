<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use App\Modules\Shared\Domain\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Payment Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('payment cannot exceed balance', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'invoice_number' => 'INV-2026-0001',
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'discount_total_cents' => 0,
        'total_cents' => 10000,
        'issued_at' => now(),
        'locked_at' => now(),
    ]);
    $service = app(InvoiceService::class);
    $service->applyPayment($invoice, Money::fromMinorUnits(5000, 'USD'));
    $invoice->refresh();
    expect($invoice->totalPaidCents())->toBe(5000);
    $service->applyPayment($invoice, Money::fromMinorUnits(6000, 'USD'));
})->throws(\InvalidArgumentException::class)->group('invoice');
