<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class CreateOrderCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $customerEmail,
        public ?string $customerId = null,
    ) {
    }
}
