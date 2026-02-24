<?php

declare(strict_types=1);

namespace App\Services\Promotion\DTOs;

/**
 * Result of promotion evaluation. Deterministic; no side effects.
 *
 * @param list<AppliedPromotionDTO> $appliedPromotions
 * @param int $totalDiscountCents
 */
final readonly class PromotionEvaluationResult
{
    public function __construct(
        public array $appliedPromotions,
        public int $totalDiscountCents,
    ) {
    }

    /** @return list<array{id: string, name: string, type: string, discount_cents: int}> */
    public function appliedPromotionsSnapshot(): array
    {
        $out = [];
        foreach ($this->appliedPromotions as $p) {
            $out[] = $p->toSnapshot();
        }
        return $out;
    }
}
