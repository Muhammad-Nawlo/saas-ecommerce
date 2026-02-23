<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Api\Resources;

use App\Modules\Catalog\Application\DTOs\ProductDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDTO
 */
final class ProductResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductDTO $dto */
        $dto = $this->resource;
        return [
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'name' => $dto->name,
            'slug' => $dto->slug,
            'description' => $dto->description,
            'price' => [
                'minor_units' => $dto->priceMinorUnits,
                'currency' => $dto->currency,
            ],
            'is_active' => $dto->isActive,
            'created_at' => $dto->createdAt,
        ];
    }
}
