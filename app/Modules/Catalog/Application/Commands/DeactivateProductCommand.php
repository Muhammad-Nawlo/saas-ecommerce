<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class DeactivateProductCommand implements Command
{
    public function __construct(
        public string $productId
    ) {
    }
}
