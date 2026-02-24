<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Services;

interface OrderCreationService
{
    /**
     * @param array<int, array{product_id: string, quantity: int, unit_price_minor_units: int, currency: string}> $items
     */
    public function createOrderFromCart(string $tenantId, string $customerEmail, array $items): string;
}
