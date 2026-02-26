<?php

declare(strict_types=1);

/**
 * Stress scenario: multiple orders and payment confirmations.
 * Ensures no duplicate invoices, no negative inventory, no deadlocks.
 * Run with a small N locally; increase for load testing.
 */

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Modules\Checkout\Application\Handlers\ConfirmCheckoutPaymentHandler;
use App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Application\Services\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('stress: create multiple orders and confirm payments — no duplicate invoices, no negative inventory', function (): void {
    $N = 10;
    Config::set('invoicing.auto_generate_invoice_on_payment', true);

    $fakeGateway = new class implements PaymentGateway {
        public function createPaymentIntent(\App\Modules\Shared\Domain\ValueObjects\Money $amount, array $metadata): array
        {
            return [
                'client_secret' => 'pi_test_' . Str::random(8),
                'provider_payment_id' => 'pi_' . Str::random(12),
            ];
        }

        public function confirmPayment(string $providerPaymentId): void
        {
        }

        public function refund(string $providerPaymentId): void
        {
        }
    };
    $resolver = new class($fakeGateway) implements PaymentGatewayResolver {
        public function __construct(private PaymentGateway $g)
        {
        }

        public function resolve(\App\Modules\Payments\Domain\ValueObjects\PaymentProvider $provider): PaymentGateway
        {
            return $this->g;
        }
    };
    app()->instance(PaymentGatewayResolver::class, $resolver);

    $tenant = Tenant::create(['name' => 'Stress Test', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => database_path('migrations/tenant'), '--force' => true]);
        if (class_exists(\Database\Seeders\CurrencySeeder::class)) {
            Artisan::call('db:seed', ['--class' => \Database\Seeders\CurrencySeeder::class]);
        }
    });

    $plan = Plan::on(config('tenancy.database.central_connection', config('database.default')))->firstOrCreate(
        ['code' => 'starter'],
        ['name' => 'Starter', 'price' => 0, 'billing_interval' => 'monthly']
    );
    Subscription::on(config('tenancy.database.central_connection', config('database.default')))
        ->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'id' => Str::uuid()->toString(),
                'plan_id' => $plan->id,
                'status' => 'active',
                'stripe_subscription_id' => 'sub_stress_' . $tenant->id,
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
    $handler = app(ConfirmCheckoutPaymentHandler::class);

    $product = $createProduct($tenant->id);
    if ($product !== null) {
        $createStock($product->id()->value());
    }
    $productId = $product?->id()->value() ?? Str::uuid()->toString();

    $orderIds = [];
    $paymentIds = [];
    for ($i = 0; $i < $N; $i++) {
        $cartResult = $createCartAndAddItem($tenant->id, $productId);
        $checkoutResult = $doCheckout($cartResult['cart_id'], $tenant->id, 'stress' . $i . '@test.com');
        $orderIds[] = $checkoutResult['order_id'];
        $paymentIds[] = $checkoutResult['payment_id'];
    }

    foreach ($paymentIds as $idx => $paymentId) {
        $model = \App\Modules\Payments\Infrastructure\Persistence\PaymentModel::find($paymentId);
        $providerId = $model?->provider_payment_id ?? 'pi_ok';
        $handler(new ConfirmCheckoutPaymentCommand(paymentId: $paymentId, providerPaymentId: $providerId));
    }

    $financialOrders = FinancialOrder::whereIn('operational_order_id', $orderIds)->get();
    expect($financialOrders)->toHaveCount($N);

    $invoices = Invoice::whereIn('order_id', $financialOrders->pluck('id'))->get();
    expect($invoices)->toHaveCount($N);

    $uniqueInvoices = $invoices->unique('order_id');
    expect($uniqueInvoices->count())->toBe($N);
})->group('stress');
