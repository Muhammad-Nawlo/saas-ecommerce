<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\DTOs;

use App\Modules\Cart\Domain\Entities\CartItem;

final readonly class CartItemDTO
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

    public static function fromCartItem(CartItem $item): self
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
