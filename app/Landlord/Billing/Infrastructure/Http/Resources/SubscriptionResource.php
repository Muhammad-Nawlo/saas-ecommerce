<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Http\Resources;

use App\Landlord\Billing\Application\DTOs\SubscriptionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SubscriptionDTO $dto */
        $dto = $this->resource;
        return [
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'plan_id' => $dto->planId,
            'stripe_subscription_id' => $dto->stripeSubscriptionId,
            'status' => $dto->status,
            'current_period_start' => $dto->currentPeriodStart,
            'current_period_end' => $dto->currentPeriodEnd,
            'cancel_at_period_end' => $dto->cancelAtPeriodEnd,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
