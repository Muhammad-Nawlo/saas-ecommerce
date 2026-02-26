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
    $this->tenant = Tenant::create(['name' => 'Credit Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('credit note cannot exceed total', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'invoice_number' => 'INV-2026-0002',
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal_cents' => 5000,
        'tax_total_cents' => 0,
        'discount_total_cents' => 0,
        'total_cents' => 5000,
        'issued_at' => now(),
        'locked_at' => now(),
    ]);
    $service = app(InvoiceService::class);
    $service->createCreditNote($invoice, Money::fromMinorUnits(6000, 'USD'), 'Refund');
})->throws(\InvalidArgumentException::class)->group('invoice');
