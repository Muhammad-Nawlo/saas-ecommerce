<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Services;

use App\Modules\Cart\Application\Services\OrderCreationService;
use App\Modules\Orders\Application\Commands\AddOrderItemCommand;
use App\Modules\Orders\Application\Commands\CreateOrderCommand;
use App\Modules\Orders\Application\Handlers\AddOrderItemHandler;
use App\Modules\Orders\Application\Handlers\CreateOrderHandler;

final readonly class CartOrderCreationService implements OrderCreationService
{
    public function __construct(
        private CreateOrderHandler $createOrderHandler,
        private AddOrderItemHandler $addOrderItemHandler
    ) {
    }

    public function createOrderFromCart(string $tenantId, string $customerEmail, array $items): string
    {
        $createCommand = new CreateOrderCommand(tenantId: $tenantId, customerEmail: $customerEmail);
        $orderId = ($this->createOrderHandler)($createCommand)->value();
        foreach ($items as $item) {
            ($this->addOrderItemHandler)(new AddOrderItemCommand(
                orderId: $orderId,
                productId: $item['product_id'],
                quantity: $item['quantity'],
                unitPriceMinorUnits: $item['unit_price_minor_units'],
                currency: $item['currency']
            ));
        }
        return $orderId;
    }
}
