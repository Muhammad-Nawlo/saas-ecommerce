<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\DTOs;

use App\Modules\Orders\Domain\Entities\OrderItem;

final readonly class OrderItemDTO
{
    public function __construct(
        public string $id,
        public string $productId,
        public int $quantity,
        public int $unitPriceMinorUnits,
        public string $currency,
        public int $totalPriceMinorUnits
    ) {
    }

    public static function fromOrderItem(OrderItem $item): self
    {
        return new self(
            $item->id()->value(),
            $item->productId()->value(),
            $item->quantity(),
            $item->unitPrice()->amountInMinorUnits(),
            $item->unitPrice()->currency(),
            $item->totalPrice()->amountInMinorUnits()
        );
    }
}
