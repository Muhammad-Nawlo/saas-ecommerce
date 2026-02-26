<?php

declare(strict_types=1);

use App\Landlord\Models\Tenant;
use App\Services\Invoice\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::create(['name' => 'Number Test', 'data' => []]);
    $this->tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($this->tenant);
});

test('invoice number unique per tenant', function (): void {
    $gen = app(InvoiceNumberGenerator::class);
    $a = $gen->generate($this->tenant->id);
    $b = $gen->generate($this->tenant->id);
    expect($a)->not->toBe($b);
    expect(preg_match('/^INV-\d{4}-\d{4}$/', $a))->toBe(1);
})->group('invoice');
