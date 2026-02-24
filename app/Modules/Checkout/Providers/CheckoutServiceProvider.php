<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Providers;

use App\Modules\Checkout\Application\Contracts\CartService;
use App\Modules\Checkout\Application\Contracts\InventoryService;
use App\Modules\Checkout\Application\Contracts\OrderService;
use App\Modules\Checkout\Application\Contracts\PaymentService;
use App\Modules\Checkout\Infrastructure\Services\CheckoutCartService;
use App\Modules\Checkout\Infrastructure\Services\CheckoutInventoryService;
use App\Modules\Checkout\Infrastructure\Services\CheckoutOrderService;
use App\Modules\Checkout\Infrastructure\Services\CheckoutPaymentService;
use Illuminate\Support\ServiceProvider;

final class CheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CartService::class, CheckoutCartService::class);
        $this->app->bind(InventoryService::class, CheckoutInventoryService::class);
        $this->app->bind(OrderService::class, CheckoutOrderService::class);
        $this->app->bind(PaymentService::class, CheckoutPaymentService::class);
    }

    public function boot(): void
    {
        //
    }
}
