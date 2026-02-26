<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Models\Ledger\LedgerTransaction;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->centralConn = config('tenancy.database.central_connection', config('database.default'));
});

/**
 * Ensures PaymentSucceeded triggers financial pipeline: FinancialOrder, Invoice, Ledger.
 * Fails if EventBus is not bound or any step is missing.
 */
test('PaymentSucceeded creates FinancialOrder, Invoice and Ledger transaction', function (): void {
    Config::set('invoicing.auto_generate_invoice_on_payment', true);

    $tenant = Tenant::create(['name' => 'Pipeline Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
        if (class_exists(\Database\Seeders\CurrencySeeder::class)) {
            Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
        }
    });

    $plan = Plan::on($this->centralConn)->firstOrCreate(
        ['code' => 'starter'],
        ['name' => 'Starter', 'price' => 0, 'billing_interval' => 'monthly']
    );
    Subscription::on($this->centralConn)->updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'id' => Str::uuid()->toString(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_pl_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);

    $orderId = Str::uuid()->toString();
    $paymentId = Str::uuid()->toString();

    OrderModel::create([
        'id' => $orderId,
        'tenant_id' => $tenant->id,
        'customer_email' => 'test@example.com',
        'status' => 'pending',
        'subtotal_cents' => 1000,
        'tax_total_cents' => 0,
        'discount_total_cents' => 0,
        'total_cents' => 1000,
        'currency' => 'USD',
        'order_number' => 'ORD-001',
    ]);

    PaymentModel::create([
        'id' => $paymentId,
        'tenant_id' => $tenant->id,
        'order_id' => $orderId,
        'amount' => 1000,
        'currency' => 'USD',
        'status' => 'succeeded',
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_test_123',
    ]);

    Event::dispatch(new PaymentSucceeded(
        PaymentId::fromString($paymentId),
        $orderId,
        new \DateTimeImmutable()
    ));

    $financialOrder = FinancialOrder::where('operational_order_id', $orderId)->first();
    expect($financialOrder)->not->toBeNull('FinancialOrder must be created by PaymentSucceeded pipeline.');
    expect($financialOrder->status)->toBe(FinancialOrder::STATUS_PAID);

    $invoice = Invoice::where('order_id', $financialOrder->id)->first();
    expect($invoice)->not->toBeNull('Invoice must be created when auto_generate_invoice_on_payment is true.');

    $ledgerTransaction = LedgerTransaction::where('reference_type', 'financial_order')
        ->where('reference_id', $financialOrder->id)
        ->first();
    expect($ledgerTransaction)->not->toBeNull('Ledger transaction must be created for paid order.');

    tenancy()->end();
})->group('security');
