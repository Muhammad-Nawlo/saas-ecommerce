<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Events;

use App\Landlord\Billing\Domain\ValueObjects\SubscriptionId;

final readonly class SubscriptionCreated
{
    public function __construct(
        public SubscriptionId $subscriptionId,
        public string $tenantId,
        public string $planId,
        public \DateTimeImmutable $occurredAt
    ) {
    }
}
