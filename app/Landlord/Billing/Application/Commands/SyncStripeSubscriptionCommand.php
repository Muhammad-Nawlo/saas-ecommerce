<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class SyncStripeSubscriptionCommand implements Command
{
    public function __construct(
        public string $stripeSubscriptionId
    ) {
    }
}
