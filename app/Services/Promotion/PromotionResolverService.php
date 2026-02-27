<?php

declare(strict_types=1);

namespace App\Services\Promotion;

use App\Models\Promotion\CouponCode;
use App\Models\Promotion\Promotion;
use App\Models\Promotion\PromotionUsage;
use App\Services\Customer\CustomerPromotionEligibilityService;
use App\Services\Promotion\DTOs\PromotionCandidateDTO;

/**
 * Resolves active promotions for a cart/order and builds candidates for PromotionEvaluationService.
 * Coupon codes restrict which promotions are considered; usage limits are pre-fetched.
 */
final readonly class PromotionResolverService
{
    public function __construct(
        private CustomerPromotionEligibilityService $eligibilityService
    ) {
    }

    /**
     * @param list<string> $couponCodes Normalized (uppercase) coupon codes; empty = consider all active
     * @return list<PromotionCandidateDTO>
     */
    public function getCandidates(
        string $tenantId,
        array $couponCodes,
        ?string $customerId,
        string $customerEmail
    ): array {
        $query = Promotion::where('tenant_id', $tenantId)->where('is_active', true);
        if ($couponCodes !== []) {
            $promotionIds = CouponCode::whereIn('code', array_map('strtoupper', $couponCodes))
                ->pluck('promotion_id')
                ->unique()
                ->all();
            if ($promotionIds === []) {
                return [];
            }
            $query->whereIn('id', $promotionIds);
        }
        $promotions = $query->get();
        $candidates = [];
        foreach ($promotions as $p) {
            $totalUses = (int) $p->usages()->count();
            $customerUses = $this->customerUsageCount($p->id, $customerId, $customerEmail);
            $candidates[] = new PromotionCandidateDTO(
                id: $p->id,
                name: $p->name,
                type: $p->type,
                valueCents: (int) $p->value_cents,
                percentage: $p->percentage,
                minCartCents: (int) $p->min_cart_cents,
                buyQuantity: $p->buy_quantity,
                getQuantity: $p->get_quantity,
                isStackable: $p->is_stackable,
                maxUsesTotal: $p->max_uses_total,
                maxUsesPerCustomer: $p->max_uses_per_customer,
                startsAt: $p->starts_at?->toDateTimeImmutable(),
                endsAt: $p->ends_at?->toDateTimeImmutable(),
                totalUses: $totalUses,
                customerUses: $customerUses
            );
        }
        return $candidates;
    }

    private function customerUsageCount(string $promotionId, ?string $customerId, string $email): int
    {
        $q = PromotionUsage::where('promotion_id', $promotionId);
        if ($customerId !== null) {
            $q->where('user_id', $customerId);
        } else {
            $q->where('customer_email', '=', strtolower($email));
        }
        return $q->count();
    }
}
