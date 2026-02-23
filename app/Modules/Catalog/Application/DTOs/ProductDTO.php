<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Domain\Entities\Product;

final readonly class ProductDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public string $slug,
        public string $description,
        public int $priceMinorUnits,
        public string $currency,
        public bool $isActive,
        public string $createdAt
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        return new self(
            $product->id()->value(),
            $product->tenantId()->value(),
            $product->name()->value(),
            $product->slug()->value(),
            $product->description()->value(),
            $product->price()->amountInMinorUnits(),
            $product->price()->currency(),
            $product->isActive(),
            $product->createdAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
