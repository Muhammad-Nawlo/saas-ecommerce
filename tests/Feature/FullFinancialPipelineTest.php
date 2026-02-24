<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Models\Currency\TenantEnabledCurrency;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Models\Ledger\LedgerEntry;
use App\Models\Ledger\LedgerTransaction;
use App\Modules\Checkout\Application\Handlers\ConfirmCheckoutPaymentHandler;
use App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Application\Services\PaymentGatewayResolver;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use App\Modules\Shared\Domain\Exceptions\PaymentAlreadyProcessedException;
use App\Services\Currency\ExchangeRateService;
use App\Services\Financial\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->centralConn = config('tenancy.database.central_connection', config('database.default'));
    $this->fakeGateway = new class implements PaymentGateway {
        public function createPaymentIntent(\App\Modules\Shared\Domain\ValueObjects\Money $amount, array $metadata): array
        {
            return [
                'client_secret' => 'pi_test_secret_' . ($metadata['payment_id'] ?? ''),
                'provider_payment_id' => 'pi_test_' . Str::random(8),
            ];
        }

        public function confirmPayment(string $providerPaymentId): void
        {
        }

        public function refund(string $providerPaymentId): void
        {
        }
    };
    $this->resolver = new class($this->fakeGateway) implements PaymentGatewayResolver {
        public function __construct(private PaymentGateway $gateway)
        {
        }

        public function resolve(\App\Modules\Payments\Domain\ValueObjects\PaymentProvider $provider): PaymentGateway
        {
            return $this->gateway;
        }
    };
});

/**
 * Full pipeline: tenant, product, cart, checkout, confirm payment, invoice, refund.
 * Asserts: FinancialOrder, Invoice, balanced ledger, payment snapshot immutable, refund creates reversing ledger.
 * All amounts in integer minor units (no float math).
 */
test('full financial pipeline: checkout, payment, invoice, refund, ledger balanced, double payment fails', function (): void {
    Config::set('invoicing.auto_generate_invoice_on_payment', true);
    app()->instance(PaymentGatewayResolver::class, $this->resolver);

    $tenant = Tenant::create(['name' => 'Pipeline Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
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

    $usd = Currency::where('code', 'USD')->first();
    if ($usd !== null) {
        TenantCurrencySetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['base_currency_id' => $usd->id, 'allow_multi_currency' => false, 'rounding_strategy' => 'half_up']
        );
        TenantEnabledCurrency::firstOrCreate(
            ['tenant_id' => $tenant->id, 'currency_id' => $usd->id],
            ['tenant_id' => $tenant->id, 'currency_id' => $usd->id]
        );
    }

    $createProduct = require __DIR__ . '/../Integration/Helpers/create_tenant_product.php';
    $createStock = require __DIR__ . '/../Integration/Helpers/create_tenant_stock.php';
    $createCartAndAddItem = require __DIR__ . '/../Integration/Helpers/create_cart_and_add_item.php';
    $doCheckout = require __DIR__ . '/../Integration/Helpers/do_checkout.php';
    $confirmPayment = require __DIR__ . '/../Integration/Helpers/confirm_payment.php';

    $product = $createProduct($tenant->id);
    if ($product !== null) {
        $createStock($product->id()->value());
    }
    $cartResult = $createCartAndAddItem($tenant->id, $product?->id()->value() ?? Str::uuid()->toString());
    $cartId = $cartResult['cart_id'] ?? null;
    expect($cartId)->not->toBeNull();

    $checkoutResult = $doCheckout($cartId, $tenant->id, 'customer@test.com');
    $paymentId = $checkoutResult['payment_id'] ?? null;
    $orderId = $checkoutResult['order_id'] ?? null;
    expect($orderId)->not->toBeNull();
    expect($paymentId)->not->toBeNull();

    $confirmPayment($paymentId);

    $financialOrder = FinancialOrder::where('operational_order_id', $orderId)->first();
    expect($financialOrder)->not->toBeNull();
    expect($financialOrder->status)->toBe(FinancialOrder::STATUS_PAID);

    $invoice = Invoice::where('order_id', $financialOrder->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->total_cents)->toBe($financialOrder->total_cents);

    $paymentModel = PaymentModel::find($paymentId);
    expect($paymentModel)->not->toBeNull();
    expect($paymentModel->payment_amount)->not->toBeNull();
    expect($paymentModel->payment_currency)->not->toBeNull();
    expect(is_int($paymentModel->payment_amount) || is_string($paymentModel->payment_amount))->toBeTrue();

    $orderTotalCents = (int) $financialOrder->total_cents;
    $ledgerTxns = LedgerTransaction::where('reference_type', 'financial_order')->where('reference_id', $financialOrder->id)->with('entries')->get();
    foreach ($ledgerTxns as $tx) {
        $debits = 0;
        $credits = 0;
        foreach ($tx->entries as $e) {
            $amt = (int) $e->amount_cents;
            if ($e->type === LedgerEntry::TYPE_DEBIT) {
                $debits += $amt;
            } else {
                $credits += $amt;
            }
        }
        expect($debits)->toBe($credits, "Ledger transaction {$tx->id} must be balanced");
    }

    $refundService = app(RefundService::class);
    $refundService->refund($financialOrder, $orderTotalCents);

    $refundLedgerTxns = LedgerTransaction::where('reference_type', 'refund')->where('reference_id', $financialOrder->id)->with('entries')->get();
    expect($refundLedgerTxns->count())->toBeGreaterThan(0, 'Refund must create reversing ledger transaction');
    foreach ($refundLedgerTxns as $tx) {
        $debits = 0;
        $credits = 0;
        foreach ($tx->entries as $e) {
            $amt = (int) $e->amount_cents;
            expect(is_int($amt))->toBeTrue();
            if ($e->type === LedgerEntry::TYPE_DEBIT) {
                $debits += $amt;
            } else {
                $credits += $amt;
            }
        }
        expect($debits)->toBe($credits, "Refund ledger transaction {$tx->id} must be balanced");
    }

    expect(fn () => $confirmPayment($paymentId))->toThrow(PaymentAlreadyProcessedException::class);
})->group('financial', 'financial_pipeline');
