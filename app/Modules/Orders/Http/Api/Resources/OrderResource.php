<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Api\Resources;

use App\Modules\Orders\Application\DTOs\OrderDTO;
use App\Modules\Orders\Application\DTOs\OrderItemDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderDTO
 */
final class OrderResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderDTO $dto */
        $dto = $this->resource;
        return [
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'customer_email' => $dto->customerEmail,
            'status' => $dto->status,
            'total_amount' => [
                'minor_units' => $dto->totalAmountMinorUnits,
                'currency' => $dto->currency,
            ],
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'items' => array_map(
                fn (OrderItemDTO $item) => [
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
