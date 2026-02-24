<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class ConfirmPaymentCommand implements Command
{
    public function __construct(
        public string $paymentId,
        public string $providerPaymentId
    ) {
    }
}
