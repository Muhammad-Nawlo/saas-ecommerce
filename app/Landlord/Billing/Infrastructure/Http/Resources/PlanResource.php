<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Http\Resources;

use App\Landlord\Billing\Application\DTOs\PlanDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PlanDTO $dto */
        $dto = $this->resource;
        return [
            'id' => $dto->id,
            'name' => $dto->name,
            'stripe_price_id' => $dto->stripePriceId,
            'price_amount' => $dto->priceAmount,
            'currency' => $dto->currency,
            'billing_interval' => $dto->billingInterval,
            'is_active' => $dto->isActive,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
