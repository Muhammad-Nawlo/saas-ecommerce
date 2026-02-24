<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\DTOs;

use App\Landlord\Billing\Domain\Entities\Subscription;

final readonly class SubscriptionDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $planId,
        public string $stripeSubscriptionId,
        public string $status,
        public string $currentPeriodStart,
        public string $currentPeriodEnd,
        public bool $cancelAtPeriodEnd,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromSubscription(Subscription $subscription): self
    {
        return new self(
            $subscription->id()->value(),
            $subscription->tenantId(),
            $subscription->planId()->value(),
            $subscription->stripeSubscriptionId()->value(),
            $subscription->status()->value(),
            $subscription->currentPeriodStart()->format(\DateTimeInterface::ATOM),
            $subscription->currentPeriodEnd()->format(\DateTimeInterface::ATOM),
            $subscription->cancelAtPeriodEnd(),
            $subscription->createdAt()->format(\DateTimeInterface::ATOM),
            $subscription->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
