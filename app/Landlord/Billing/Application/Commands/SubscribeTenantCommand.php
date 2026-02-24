<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class SubscribeTenantCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $planId,
        public string $customerEmail
    ) {
    }
}
