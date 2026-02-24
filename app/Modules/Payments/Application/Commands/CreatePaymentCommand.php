<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class CreatePaymentCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
        public int $amountMinorUnits,
        public string $currency,
        public string $provider
    ) {
    }
}
