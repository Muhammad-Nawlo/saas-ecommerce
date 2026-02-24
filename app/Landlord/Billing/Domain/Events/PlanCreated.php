<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Events;

use App\Landlord\Billing\Domain\ValueObjects\PlanId;

final readonly class PlanCreated
{
    public function __construct(
        public PlanId $planId,
        public string $name,
        public \DateTimeImmutable $occurredAt
    ) {
    }
}
