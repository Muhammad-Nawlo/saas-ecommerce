<?php

declare(strict_types=1);

namespace App\Services\Promotion;

use App\Models\Promotion\PromotionUsage;

/**
 * Records promotion usage when an order is paid. Idempotent per order.
 */
final class RecordPromotionUsageService
{
    /**
     * Record usage for each applied promotion. Call after order is paid.
     *
     * @param array<int, array{id: string, name: string, type: string, discount_cents: int}> $appliedPromotions
     */
    public function recordForOrder(
        string $orderId,
        ?string $customerId,
        string $customerEmail,
        array $appliedPromotions
    ): void {
        foreach ($appliedPromotions as $p) {
            $promotionId = $p['id'] ?? null;
            if ($promotionId === null) {
                continue;
            }
            $exists = PromotionUsage::where('promotion_id', $promotionId)
                ->where('order_id', $orderId)
                ->exists();
            if ($exists) {
                continue;
            }
            PromotionUsage::create([
                'promotion_id' => $promotionId,
                'customer_id' => $customerId,
                'customer_email' => strtolower($customerEmail),
                'order_id' => $orderId,
                'used_at' => now(),
            ]);
        }
    }
}
