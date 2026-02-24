<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class CreateCartCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public ?string $customerEmail,
        public ?string $sessionId
    ) {
    }
}
