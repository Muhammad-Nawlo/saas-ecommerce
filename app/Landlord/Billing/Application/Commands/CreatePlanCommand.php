<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class CreatePlanCommand implements Command
{
    public function __construct(
        public string $name,
        public string $stripePriceId,
        public int $priceAmount,
        public string $currency,
        public string $billingInterval
    ) {
    }
}
