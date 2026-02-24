<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use App\Modules\Shared\Domain\Exceptions\FinancialOrderLockedException;
use App\Modules\Shared\Domain\Exceptions\InvoiceLockedException;
use App\Modules\Shared\Domain\Exceptions\PaymentConfirmedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('FinancialOrder throws when mutating locked order', function (): void {
    $tenant = Tenant::create(['name' => 'Immutable FO', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FIN-LOCK-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
        'snapshot' => ['total_cents' => 10000],
    ]);

    $order->total_cents = 9999;
    expect(fn () => $order->save())->toThrow(FinancialOrderLockedException::class);
})->group('financial', 'financial_immutability');

test('Invoice throws when mutating total after issued', function (): void {
    $tenant = Tenant::create(['name' => 'Immutable Inv', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'invoice_number' => 'INV-001',
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'discount_total_cents' => 0,
        'total_cents' => 10000,
    ]);

    $invoice->total_cents = 9999;
    expect(fn () => $invoice->save())->toThrow(InvoiceLockedException::class);
})->group('financial', 'financial_immutability');

test('PaymentModel throws when mutating amount after confirmed', function (): void {
    $tenant = Tenant::create(['name' => 'Immutable Pay', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $payment = PaymentModel::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'order_id' => Str::uuid()->toString(),
        'amount' => 10000,
        'currency' => 'USD',
        'status' => PaymentModel::STATUS_SUCCEEDED,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_ok',
    ]);

    $payment->amount = 9999;
    expect(fn () => $payment->save())->toThrow(PaymentConfirmedException::class);
})->group('financial', 'financial_immutability');
