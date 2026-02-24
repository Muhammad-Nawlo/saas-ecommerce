<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Contracts;

interface OrderService
{
    /**
     * @param array{tenant_id: string, customer_email: string, items: array<int, array{product_id: string, quantity: int, unit_price_minor_units: int, currency: string}>} $cartData
     */
    public function createOrderFromCart(array $cartData): string;
}
