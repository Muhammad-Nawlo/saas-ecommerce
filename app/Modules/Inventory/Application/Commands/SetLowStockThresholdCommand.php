<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class SetLowStockThresholdCommand implements Command
{
    public function __construct(
        public string $productId,
        public int $threshold
    ) {
    }
}
