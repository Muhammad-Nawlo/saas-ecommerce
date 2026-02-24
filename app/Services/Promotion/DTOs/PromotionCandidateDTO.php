<?php

declare(strict_types=1);

namespace App\Services\Promotion\DTOs;

/**
 * Data for promotion evaluation. No side effects; caller provides usage counts.
 */
final readonly class PromotionCandidateDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public int $valueCents,
        public ?float $percentage,
        public int $minCartCents,
        public ?int $buyQuantity,
        public ?int $getQuantity,
        public bool $isStackable,
        public ?int $maxUsesTotal,
        public ?int $maxUsesPerCustomer,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public int $totalUses,
        public int $customerUses,
    ) {
    }
}
