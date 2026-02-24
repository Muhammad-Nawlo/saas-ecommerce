<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Promotion;

use App\Models\Promotion\Promotion;
use App\Services\Promotion\DTOs\PromotionCandidateDTO;
use App\Services\Promotion\PromotionEvaluationService;
use PHPUnit\Framework\TestCase;

class PromotionEvaluationServiceTest extends TestCase
{
    private PromotionEvaluationService $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new PromotionEvaluationService(new \DateTimeImmutable('2025-06-15 12:00:00'));
    }

    public function test_percentage_discount_applies(): void
    {
        $candidates = [
            new PromotionCandidateDTO(
                id: 'p1',
                name: '10% off',
                type: Promotion::TYPE_PERCENTAGE,
                valueCents: 0,
                percentage: 10.0,
                minCartCents: 1000,
                buyQuantity: null,
                getQuantity: null,
                isStackable: false,
                maxUsesTotal: null,
                maxUsesPerCustomer: null,
                startsAt: null,
                endsAt: null,
                totalUses: 0,
                customerUses: 0
            ),
        ];
        $result = $this->evaluator->evaluate(5000, [['quantity' => 1, 'unit_price_cents' => 5000]], $candidates, 'USD');
        self::assertSame(500, $result->totalDiscountCents);
        self::assertCount(1, $result->appliedPromotions);
        self::assertSame(500, $result->appliedPromotions[0]->discountCents);
    }

    public function test_fixed_discount_capped_at_subtotal(): void
    {
        $candidates = [
            new PromotionCandidateDTO(
                id: 'p1',
                name: '500 off',
                type: Promotion::TYPE_FIXED,
                valueCents: 500,
                percentage: null,
                minCartCents: 0,
                buyQuantity: null,
                getQuantity: null,
                isStackable: false,
                maxUsesTotal: null,
                maxUsesPerCustomer: null,
                startsAt: null,
                endsAt: null,
                totalUses: 0,
                customerUses: 0
            ),
        ];
        $result = $this->evaluator->evaluate(300, [['quantity' => 1, 'unit_price_cents' => 300]], $candidates, 'USD');
        self::assertSame(300, $result->totalDiscountCents);
    }

    public function test_min_cart_threshold_not_met_skips(): void
    {
        $candidates = [
            new PromotionCandidateDTO(
                id: 'p1',
                name: '10% off',
                type: Promotion::TYPE_PERCENTAGE,
                valueCents: 0,
                percentage: 10.0,
                minCartCents: 10000,
                buyQuantity: null,
                getQuantity: null,
                isStackable: false,
                maxUsesTotal: null,
                maxUsesPerCustomer: null,
                startsAt: null,
                endsAt: null,
                totalUses: 0,
                customerUses: 0
            ),
        ];
        $result = $this->evaluator->evaluate(5000, [], $candidates, 'USD');
        self::assertSame(0, $result->totalDiscountCents);
        self::assertCount(0, $result->appliedPromotions);
    }

    public function test_expired_promotion_skipped(): void
    {
        $candidates = [
            new PromotionCandidateDTO(
                id: 'p1',
                name: '10% off',
                type: Promotion::TYPE_PERCENTAGE,
                valueCents: 0,
                percentage: 10.0,
                minCartCents: 0,
                buyQuantity: null,
                getQuantity: null,
                isStackable: false,
                maxUsesTotal: null,
                maxUsesPerCustomer: null,
                startsAt: null,
                endsAt: new \DateTimeImmutable('2025-06-01'),
                totalUses: 0,
                customerUses: 0
            ),
        ];
        $result = $this->evaluator->evaluate(5000, [], $candidates, 'USD');
        self::assertSame(0, $result->totalDiscountCents);
    }

    public function test_snapshot_format(): void
    {
        $candidates = [
            new PromotionCandidateDTO(
                id: 'pid',
                name: 'Test',
                type: Promotion::TYPE_FIXED,
                valueCents: 100,
                percentage: null,
                minCartCents: 0,
                buyQuantity: null,
                getQuantity: null,
                isStackable: false,
                maxUsesTotal: null,
                maxUsesPerCustomer: null,
                startsAt: null,
                endsAt: null,
                totalUses: 0,
                customerUses: 0
            ),
        ];
        $result = $this->evaluator->evaluate(500, [], $candidates, 'USD');
        $snap = $result->appliedPromotionsSnapshot();
        self::assertSame([['id' => 'pid', 'name' => 'Test', 'type' => 'fixed', 'discount_cents' => 100]], $snap);
    }
}
