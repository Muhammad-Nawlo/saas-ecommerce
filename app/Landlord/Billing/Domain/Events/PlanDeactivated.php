<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Events;

use App\Landlord\Billing\Domain\ValueObjects\PlanId;

final readonly class PlanDeactivated
{
    public function __construct(
        public PlanId $planId,
        public \DateTimeImmutable $occurredAt
    ) {
    }
}
