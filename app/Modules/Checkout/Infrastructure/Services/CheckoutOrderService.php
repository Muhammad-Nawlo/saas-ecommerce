<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Services;

use App\Modules\Cart\Application\Services\OrderCreationService;
use App\Modules\Checkout\Application\Contracts\OrderService;

final readonly class CheckoutOrderService implements OrderService
{
    public function __construct(
        private OrderCreationService $orderCreationService
    ) {
    }

    public function createOrderFromCart(array $cartData): string
    {
        $tenantId = $cartData['tenant_id'] ?? '';
        $customerEmail = $cartData['customer_email'] ?? '';
        $items = $cartData['items'] ?? [];
        return $this->orderCreationService->createOrderFromCart($tenantId, $customerEmail, $items);
    }
}
