<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class ConfirmCheckoutPaymentCommand implements Command
{
    public function __construct(
        public string $paymentId,
        public string $providerPaymentId = ''
    ) {
    }
}
