<?php

declare(strict_types=1);

namespace App\Services\Promotion\DTOs;

/**
 * One applied promotion for snapshot. Immutable after order lock.
 */
final readonly class AppliedPromotionDTO
{
    public function __construct(
        public string $promotionId,
        public string $name,
        public string $type,
        public int $discountCents,
    ) {
    }

    /** @return array{id: string, name: string, type: string, discount_cents: int} */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->promotionId,
            'name' => $this->name,
            'type' => $this->type,
            'discount_cents' => $this->discountCents,
        ];
    }
}
