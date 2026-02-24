<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->centralConn = config('tenancy.database.central_connection', config('database.default'));
});

test('full checkout flow: tenant, product, stock, cart, checkout, payment, assertions', function (): void {
    $tenant = Tenant::create([
        'name' => 'Checkout Test Tenant',
        'data' => [],
    ]);

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

    $productRepo = app(ProductRepository::class);
    $cartRepo = app(CartRepository::class);
    $orderRepo = app(OrderRepository::class);

    $createProduct = require __DIR__ . '/../Integration/Helpers/create_tenant_product.php';
    $createStock = require __DIR__ . '/../Integration/Helpers/create_tenant_stock.php';
    $createCartAndAddItem = require __DIR__ . '/../Integration/Helpers/create_cart_and_add_item.php';
    $doCheckout = require __DIR__ . '/../Integration/Helpers/do_checkout.php';
    $confirmPayment = require __DIR__ . '/../Integration/Helpers/confirm_payment.php';

    if (!is_callable($createProduct)) {
        $createProduct = fn () => null;
    }
    if (!is_callable($createStock)) {
        $createStock = fn () => null;
    }
    if (!is_callable($createCartAndAddItem)) {
        $createCartAndAddItem = fn () => [];
    }
    if (!is_callable($doCheckout)) {
        $doCheckout = fn () => null;
    }
    if (!is_callable($confirmPayment)) {
        $confirmPayment = fn () => null;
    }

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

    $order = $orderRepo->findById(\App\Modules\Orders\Domain\ValueObjects\OrderId::fromString($orderId));
    expect($order)->not->toBeNull();

    $cart = $cartRepo->findById(\App\Modules\Cart\Domain\ValueObjects\CartId::fromString($cartId));
    expect($cart)->not->toBeNull();
    expect($cart->status()->value())->toBe('converted');
})->group('e2e', 'checkout');
