<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;

/**
 * For promotion engine: first_purchase rule, usage_limit_per_customer, customer_email targeting.
 * Use customer_id when authenticated, fallback to email for guest checkout.
 */
final class CustomerPromotionEligibilityService
{
    public function orderCountForCustomer(?string $customerId, string $email): int
    {
        $tenantId = (string) tenant('id');
        if ($tenantId === '') {
            return 0;
        }
        $query = OrderModel::forTenant($tenantId)->where('customer_email', strtolower($email));
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }
        return $query->count();
    }

    public function hasPlacedOrder(?string $customerId, string $email): bool
    {
        return $this->orderCountForCustomer($customerId, $email) > 0;
    }
}
