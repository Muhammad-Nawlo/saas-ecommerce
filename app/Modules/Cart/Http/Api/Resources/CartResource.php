<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Api\Resources;

use App\Modules\Cart\Application\DTOs\CartDTO;
use App\Modules\Cart\Application\DTOs\CartItemDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartDTO
 */
final class CartResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartDTO $dto */
        $dto = $this->resource;
        return [
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'customer_email' => $dto->customerEmail,
            'session_id' => $dto->sessionId,
            'status' => $dto->status,
            'total_amount' => [
                'minor_units' => $dto->totalAmountMinorUnits,
                'currency' => $dto->currency,
            ],
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'items' => array_map(
                fn (CartItemDTO $item) => [
                    'id' => $item->id,
                    'product_id' => $item->productId,
                    'quantity' => $item->quantity,
                    'unit_price' => [
                        'minor_units' => $item->unitPriceMinorUnits,
                        'currency' => $item->currency,
                    ],
                    'total_price' => [
                        'minor_units' => $item->totalPriceMinorUnits,
                        'currency' => $item->currency,
                    ],
                ],
                $dto->items
            ),
        ];
    }
}
