<?php

declare(strict_types=1);

namespace App\Services\Promotion;

use App\Models\Promotion\Promotion;
use App\Services\Promotion\DTOs\AppliedPromotionDTO;
use App\Services\Promotion\DTOs\PromotionCandidateDTO;
use App\Services\Promotion\DTOs\PromotionEvaluationResult;

/**
 * Deterministic promotion evaluation. Pure function style; no side effects.
 * Caller provides cart subtotal, items (for BOGO), and promotion candidates with usage counts.
 * Applied promotions are snapshot-safe for order lock.
 */
final class PromotionEvaluationService
{
    private \DateTimeImmutable $now;

    public function __construct(?\DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new \DateTimeImmutable();
    }

    /**
     * Evaluate which promotions apply and total discount in minor units.
     *
     * @param int $subtotalCents Cart subtotal (sum of item totals) in minor units
     * @param array<int, array{quantity: int, unit_price_cents: int}> $items For BOGO/threshold
     * @param list<PromotionCandidateDTO> $candidates Active promotions with pre-fetched usage
     * @param string $currency Order currency (for consistency)
     * @return PromotionEvaluationResult
     */
    public function evaluate(
        int $subtotalCents,
        array $items,
        array $candidates,
        string $currency
    ): PromotionEvaluationResult {
        $applicable = [];
        foreach ($candidates as $c) {
            if (!$this->isValid($c)) {
                continue;
            }
            if ($subtotalCents < $c->minCartCents) {
                continue;
            }
            if ($c->maxUsesTotal !== null && $c->totalUses >= $c->maxUsesTotal) {
                continue;
            }
            if ($c->maxUsesPerCustomer !== null && $c->customerUses >= $c->maxUsesPerCustomer) {
                continue;
            }
            $applicable[] = $c;
        }

        $stackable = array_filter($applicable, fn (PromotionCandidateDTO $c) => $c->isStackable);
        $exclusive = array_filter($applicable, fn (PromotionCandidateDTO $c) => !$c->isStackable);

        $toApply = [];
        if (count($exclusive) > 0) {
            $best = $this->bestDiscount($subtotalCents, $items, $exclusive);
            if ($best !== null) {
                $toApply[] = $best;
            }
        }
        foreach ($stackable as $c) {
            $dto = $this->applyOne($subtotalCents, $items, $c);
            if ($dto !== null) {
                $toApply[] = $dto;
            }
        }

        $totalDiscountCents = 0;
        $remainingSubtotal = $subtotalCents;
        $applied = [];
        foreach ($toApply as $dto) {
            $cap = min($dto->discountCents, $remainingSubtotal);
            if ($cap <= 0) {
                continue;
            }
            $totalDiscountCents += $cap;
            $remainingSubtotal -= $cap;
            $applied[] = new AppliedPromotionDTO(
                $dto->promotionId,
                $dto->name,
                $dto->type,
                $cap
            );
        }
        $totalDiscountCents = min($totalDiscountCents, $subtotalCents);

        return new PromotionEvaluationResult($applied, $totalDiscountCents);
    }

    private function isValid(PromotionCandidateDTO $c): bool
    {
        if ($c->startsAt !== null && $this->now < $c->startsAt) {
            return false;
        }
        if ($c->endsAt !== null && $this->now > $c->endsAt) {
            return false;
        }
        return true;
    }

    /**
     * @param list<PromotionCandidateDTO> $candidates
     */
    private function bestDiscount(int $subtotalCents, array $items, array $candidates): ?AppliedPromotionDTO
    {
        $best = null;
        $bestCents = 0;
        foreach ($candidates as $c) {
            $dto = $this->applyOne($subtotalCents, $items, $c);
            if ($dto !== null && $dto->discountCents > $bestCents) {
                $best = $dto;
                $bestCents = $dto->discountCents;
            }
        }
        return $best;
    }

    private function applyOne(int $subtotalCents, array $items, PromotionCandidateDTO $c): ?AppliedPromotionDTO
    {
        $discount = match ($c->type) {
            Promotion::TYPE_PERCENTAGE => $c->percentage !== null
                ? (int) round($subtotalCents * $c->percentage / 100)
                : 0,
            Promotion::TYPE_FIXED => min($c->valueCents, $subtotalCents),
            Promotion::TYPE_THRESHOLD => $subtotalCents >= $c->minCartCents ? min($c->valueCents, $subtotalCents) : 0,
            Promotion::TYPE_FREE_SHIPPING => min($c->valueCents, $subtotalCents),
            Promotion::TYPE_BOGO => $this->bogoDiscount($items, $c),
            default => 0,
        };
        if ($discount <= 0) {
            return null;
        }
        return new AppliedPromotionDTO($c->id, $c->name, $c->type, $discount);
    }

    /**
     * @param array<int, array{quantity: int, unit_price_cents: int}> $items
     */
    private function bogoDiscount(array $items, PromotionCandidateDTO $c): int
    {
        if ($c->buyQuantity === null || $c->getQuantity === null) {
            return 0;
        }
        $totalQty = 0;
        $cheapestUnitCents = null;
        foreach ($items as $item) {
            $totalQty += $item['quantity'];
            $up = $item['unit_price_cents'];
            if ($cheapestUnitCents === null || $up < $cheapestUnitCents) {
                $cheapestUnitCents = $up;
            }
        }
        if ($cheapestUnitCents === null || $totalQty < $c->buyQuantity) {
            return 0;
        }
        $sets = (int) floor($totalQty / ($c->buyQuantity + $c->getQuantity));
        $freeQty = $sets * $c->getQuantity;
        return $freeQty * $cheapestUnitCents;
    }

    public static function create(): self
    {
        return new self(new \DateTimeImmutable());
    }
}
