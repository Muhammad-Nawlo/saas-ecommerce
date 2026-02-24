<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\DTOs;

use App\Landlord\Billing\Domain\Entities\Plan;

final readonly class PlanDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $stripePriceId,
        public int $priceAmount,
        public string $currency,
        public string $billingInterval,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromPlan(Plan $plan): self
    {
        return new self(
            $plan->id()->value(),
            $plan->name(),
            $plan->stripePriceId(),
            $plan->priceAmount(),
            $plan->currency(),
            $plan->billingInterval()->value(),
            $plan->isActive(),
            $plan->createdAt()->format(\DateTimeInterface::ATOM),
            $plan->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
