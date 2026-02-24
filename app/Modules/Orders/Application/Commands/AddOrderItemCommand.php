<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class AddOrderItemCommand implements Command
{
    public function __construct(
        public string $orderId,
        public string $productId,
        public int $quantity,
        public int $unitPriceMinorUnits,
        public string $currency
    ) {
    }
}
