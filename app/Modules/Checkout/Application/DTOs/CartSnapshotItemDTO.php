<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\DTOs;

final readonly class CartSnapshotItemDTO
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public int $unitPriceMinorUnits,
        public string $currency
    ) {
    }
}
