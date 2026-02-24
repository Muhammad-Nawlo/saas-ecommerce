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
use App\Modules\Checkout\Application\Handlers\ConfirmCheckoutPaymentHandler;
use App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Application\Services\PaymentGatewayResolver;
use App\Modules\Shared\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Shared\Domain\Exceptions\PaymentAlreadyProcessedException;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->centralConn = config('tenancy.database.central_connection', config('database.default'));
    $this->fakeGateway = new class implements PaymentGateway {
        public function createPaymentIntent(Money $amount, array $metadata): array
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

test('full financial integrity: multi-currency tenant, checkout, payment snapshot, invoice, no duplicate on double confirm', function (): void {
    Config::set('invoicing.auto_generate_invoice_on_payment', true);
    app()->instance(PaymentGatewayResolver::class, $this->resolver);

    $tenant = Tenant::create(['name' => 'Financial Integrity Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
        Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
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
            'stripe_subscription_id' => 'sub_fi_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);

    $usd = Currency::where('code', 'USD')->first();
    $eur = Currency::where('code', 'EUR')->first();
    TenantCurrencySetting::updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'base_currency_id' => $usd->id,
            'allow_multi_currency' => true,
            'rounding_strategy' => 'half_up',
        ]
    );
    TenantEnabledCurrency::firstOrCreate(
        ['tenant_id' => $tenant->id, 'currency_id' => $usd->id],
        ['tenant_id' => $tenant->id, 'currency_id' => $usd->id]
    );
    TenantEnabledCurrency::firstOrCreate(
        ['tenant_id' => $tenant->id, 'currency_id' => $eur->id],
        ['tenant_id' => $tenant->id, 'currency_id' => $eur->id]
    );
    app(ExchangeRateService::class)->setManualRate($usd, $eur, 0.92);

    $createProduct = require __DIR__ . '/../Integration/Helpers/create_tenant_product.php';
    $createStock = require __DIR__ . '/../Integration/Helpers/create_tenant_stock.php';
    $createCartAndAddItem = require __DIR__ . '/../Integration/Helpers/create_cart_and_add_item.php';
    $doCheckout = require __DIR__ . '/../Integration/Helpers/do_checkout.php';

    $product = $createProduct($tenant->id);
    if ($product !== null) {
        $createStock($product->id()->value());
    }
    $cartResult = $createCartAndAddItem($tenant->id, $product?->id()->value() ?? Str::uuid()->toString());
    $checkoutResult = $doCheckout($cartResult['cart_id'], $tenant->id, 'customer@test.com');
    $paymentId = $checkoutResult['payment_id'];
    $orderId = $checkoutResult['order_id'];
    expect($orderId)->not->toBeNull();
    expect($paymentId)->not->toBeNull();

    $paymentModel = \App\Modules\Payments\Infrastructure\Persistence\PaymentModel::find($paymentId);
    $providerPaymentId = $paymentModel?->provider_payment_id ?? 'pi_test_ok';
    app(ConfirmCheckoutPaymentHandler::class)(new ConfirmCheckoutPaymentCommand(
        paymentId: $paymentId,
        providerPaymentId: $providerPaymentId
    ));

    $orderRepo = app(OrderRepository::class);
    $order = $orderRepo->findById(\App\Modules\Orders\Domain\ValueObjects\OrderId::fromString($orderId));
    expect($order)->not->toBeNull();
    expect($order->status()->value())->toBe('paid');
    expect($order->totalAmount()->getMinorUnits())->toBeGreaterThan(0);

    $financialOrder = FinancialOrder::where('operational_order_id', $orderId)->first();
    expect($financialOrder)->not->toBeNull();
    expect($financialOrder->status)->toBe(FinancialOrder::STATUS_PAID);
    expect($financialOrder->snapshot)->toBeArray();
    expect($financialOrder->locked_at)->not->toBeNull();
    expect($financialOrder->base_currency)->not->toBeNull();
    expect($financialOrder->display_currency)->not->toBeNull();
    expect($financialOrder->exchange_rate_snapshot)->toBeArray();
    expect($financialOrder->total_cents)->toBe($order->totalAmount()->getMinorUnits());

    $paymentModel->refresh();
    expect($paymentModel->payment_currency)->not->toBeNull();
    expect($paymentModel->payment_amount)->not->toBeNull();
    expect($paymentModel->exchange_rate_snapshot)->toBeArray();
    expect($paymentModel->payment_amount_base)->not->toBeNull();

    $invoice = Invoice::where('order_id', $financialOrder->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->total_cents)->toBe($financialOrder->total_cents);

    expect(fn () => app(ConfirmCheckoutPaymentHandler::class)(new ConfirmCheckoutPaymentCommand(
        paymentId: $paymentId,
        providerPaymentId: $providerPaymentId
    )))->toThrow(PaymentAlreadyProcessedException::class);
})->group('financial_integrity');

test('currency mismatch throws CurrencyMismatchException', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(500, 'EUR');
    $a->add($b);
})->throws(CurrencyMismatchException::class)->group('financial_integrity');

test('financial order immutable after lock: financial fields not updated', function (): void {
    $tenant = Tenant::create(['name' => 'Immutable Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $order = FinancialOrder::create([
        'tenant_id' => $tenant->id,
        'order_number' => 'FIN-IMM-' . Str::random(6),
        'currency' => 'USD',
        'subtotal_cents' => 10000,
        'tax_total_cents' => 0,
        'total_cents' => 10000,
        'status' => FinancialOrder::STATUS_PAID,
        'locked_at' => now(),
        'snapshot' => ['subtotal_cents' => 10000, 'total_cents' => 10000, 'currency' => 'USD'],
    ]);

    $order->subtotal_cents = 9999;
    $order->total_cents = 9999;
    $order->save();

    $order->refresh();
    expect($order->subtotal_cents)->toBe(10000);
    expect($order->total_cents)->toBe(10000);
})->group('financial_integrity');
