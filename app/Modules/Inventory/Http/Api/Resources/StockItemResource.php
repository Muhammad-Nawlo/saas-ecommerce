<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Api\Resources;

use App\Modules\Inventory\Application\DTOs\StockItemDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockItemDTO
 */
final class StockItemResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StockItemDTO $dto */
        $dto = $this->resource;
        return [
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'product_id' => $dto->productId,
            'quantity' => $dto->quantity,
            'reserved_quantity' => $dto->reservedQuantity,
            'low_stock_threshold' => $dto->lowStockThreshold,
            'available_quantity' => $dto->availableQuantity,
            'is_in_stock' => $dto->isInStock,
            'is_low_stock' => $dto->isLowStock,
            'created_at' => $dto->createdAt,
        ];
    }
}
