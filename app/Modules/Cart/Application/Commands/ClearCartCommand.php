<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class ClearCartCommand implements Command
{
    public function __construct(
        public string $cartId
    ) {
    }
}
