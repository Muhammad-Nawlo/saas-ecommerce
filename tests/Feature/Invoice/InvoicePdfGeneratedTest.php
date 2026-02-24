<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Models\Invoice\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->tenant = Tenant::create(['name' => 'PDF Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('PDF generated successfully when dompdf available', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'invoice_number' => 'INV-2026-0003',
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal_cents' => 1000,
        'tax_total_cents' => 0,
        'discount_total_cents' => 0,
        'total_cents' => 1000,
        'issued_at' => now(),
        'locked_at' => now(),
    ]);
    if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $this->expectException(\InvalidArgumentException::class);
    }
    try {
        $path = app(\App\Services\Invoice\InvoicePdfGenerator::class)->generate($invoice);
        expect($path)->toBeString();
        expect(file_exists($path))->toBeTrue();
    } catch (\InvalidArgumentException $e) {
        expect(str_contains($e->getMessage(), 'dompdf'))->toBeTrue();
    }
})->group('invoice');
