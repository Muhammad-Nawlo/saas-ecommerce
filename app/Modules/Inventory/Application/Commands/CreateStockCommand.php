<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class CreateStockCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $productId,
        public int $quantity,
        public int $lowStockThreshold
    ) {
    }
}
