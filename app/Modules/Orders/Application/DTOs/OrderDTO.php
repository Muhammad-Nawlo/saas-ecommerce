<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\DTOs;

use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Entities\OrderItem;

final readonly class OrderDTO
{
    /**
     * @param list<OrderItemDTO> $items
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $customerEmail,
        public string $status,
        public int $totalAmountMinorUnits,
        public string $currency,
        public string $createdAt,
        public string $updatedAt,
        public array $items
    ) {
    }

    public static function fromOrder(Order $order): self
    {
        $items = array_map(
            fn (OrderItem $item) => OrderItemDTO::fromOrderItem($item),
            $order->items()
        );
        return new self(
            $order->id()->value(),
            $order->tenantId()->value(),
            $order->customerEmail()->value(),
            $order->status()->value(),
            $order->totalAmount()->amountInMinorUnits(),
            $order->totalAmount()->currency(),
            $order->createdAt()->format(\DateTimeInterface::ATOM),
            $order->updatedAt()->format(\DateTimeInterface::ATOM),
            $items
        );
    }
}
