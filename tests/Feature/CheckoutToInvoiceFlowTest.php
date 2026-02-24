<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialTransaction;
use App\Models\Invoice\Invoice;
use App\Modules\Checkout\Application\Handlers\ConfirmCheckoutPaymentHandler;
use App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Application\Services\PaymentGatewayResolver;
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
        public function __construct(
            private PaymentGateway $gateway
        ) {
        }

        public function resolve(\App\Modules\Payments\Domain\ValueObjects\PaymentProvider $provider): PaymentGateway
        {
            return $this->gateway;
        }
    };
});

test('checkout to invoice flow: order paid, financial order synced, invoice and transaction created', function (): void {
    Config::set('invoicing.auto_generate_invoice_on_payment', true);
    app()->instance(PaymentGatewayResolver::class, $this->resolver);

    $tenant = Tenant::create(['name' => 'Checkout Invoice Test', 'data' => []]);
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
            'stripe_subscription_id' => 'sub_e2e_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);

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

    $financialOrder = FinancialOrder::where('operational_order_id', $orderId)->first();
    expect($financialOrder)->not->toBeNull();
    expect($financialOrder->status)->toBe(FinancialOrder::STATUS_PAID);
    expect($financialOrder->snapshot)->toBeArray();
    expect($financialOrder->locked_at)->not->toBeNull();
    expect($financialOrder->base_currency)->not->toBeNull();
    expect($financialOrder->display_currency)->not->toBeNull();
    expect($financialOrder->exchange_rate_snapshot)->toBeArray();

    $invoice = Invoice::where('order_id', $financialOrder->id)->first();
    expect($invoice)->not->toBeNull();

    $transaction = FinancialTransaction::where('order_id', $financialOrder->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(FinancialTransaction::STATUS_COMPLETED);
})->group('checkout', 'phase1');

test('double payment confirm is idempotent: no duplicate financial order or invoice', function (): void {
    app()->instance(PaymentGatewayResolver::class, $this->resolver);

    $tenant = Tenant::create(['name' => 'Idempotent Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
        if (class_exists(\Database\Seeders\CurrencySeeder::class)) {
            Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
        }
    });

    Plan::on($this->centralConn)->firstOrCreate(
        ['code' => 'starter'],
        ['name' => 'Starter', 'price' => 0, 'billing_interval' => 'monthly']
    );
    Subscription::on($this->centralConn)->updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'id' => Str::uuid()->toString(),
            'plan_id' => Plan::on($this->centralConn)->first()->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_' . $tenant->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]
    );

    tenancy()->initialize($tenant);

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
    $paymentModel = \App\Modules\Payments\Infrastructure\Persistence\PaymentModel::find($paymentId);
    $providerId = $paymentModel?->provider_payment_id ?? 'pi_test_ok';

    $confirmHandler = app(ConfirmCheckoutPaymentHandler::class);
    $confirmHandler(new ConfirmCheckoutPaymentCommand(paymentId: $paymentId, providerPaymentId: $providerId));
    $countAfterFirst = FinancialOrder::where('operational_order_id', $orderId)->count();
    $foFirst = FinancialOrder::where('operational_order_id', $orderId)->first();
    $invoiceCountAfterFirst = $foFirst ? Invoice::where('order_id', $foFirst->id)->count() : 0;

    $confirmHandler(new ConfirmCheckoutPaymentCommand(paymentId: $paymentId, providerPaymentId: $providerId));

    $countAfterSecond = FinancialOrder::where('operational_order_id', $orderId)->count();
    $financialOrder = FinancialOrder::where('operational_order_id', $orderId)->first();
    $invoiceCountAfterSecond = $financialOrder ? Invoice::where('order_id', $financialOrder->id)->count() : 0;

    expect($countAfterSecond)->toBe($countAfterFirst);
    expect($countAfterSecond)->toBe(1);
    expect($invoiceCountAfterSecond)->toBe($invoiceCountAfterFirst);
})->group('checkout', 'phase1');
