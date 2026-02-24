<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\DTOs;

use App\Modules\Inventory\Domain\Entities\StockItem;

final readonly class StockItemDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $productId,
        public int $quantity,
        public int $reservedQuantity,
        public int $lowStockThreshold,
        public int $availableQuantity,
        public bool $isInStock,
        public bool $isLowStock,
        public string $createdAt
    ) {
    }

    public static function fromStockItem(StockItem $stock): self
    {
        return new self(
            $stock->id()->value(),
            $stock->tenantId()->value(),
            $stock->productId()->value(),
            $stock->quantity(),
            $stock->reservedQuantity(),
            $stock->lowStockThreshold(),
            $stock->availableQuantity(),
            $stock->isInStock(),
            $stock->isLowStock(),
            $stock->createdAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
