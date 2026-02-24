<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\Repositories\StockItemRepository;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;

final readonly class InventoryStockService
{
    public function __construct(
        private StockItemRepository $stockItemRepository
    ) {
    }

    public function reserve(string $productId, int $quantity): void
    {
        $pid = ProductId::fromString($productId);
        $stock = $this->stockItemRepository->findByProductId($pid);
        if ($stock === null) {
            throw new \App\Modules\Shared\Domain\Exceptions\DomainException('Stock item not found for product');
        }
        $stock->reserve($quantity);
        $this->stockItemRepository->save($stock);
    }

    public function release(string $productId, int $quantity): void
    {
        $pid = ProductId::fromString($productId);
        $stock = $this->stockItemRepository->findByProductId($pid);
        if ($stock === null) {
            throw new \App\Modules\Shared\Domain\Exceptions\DomainException('Stock item not found for product');
        }
        $stock->release($quantity);
        $this->stockItemRepository->save($stock);
    }

    public function getAvailableQuantity(string $productId): int
    {
        $pid = ProductId::fromString($productId);
        $stock = $this->stockItemRepository->findByProductId($pid);
        if ($stock === null) {
            return 0;
        }
        return $stock->availableQuantity();
    }
}
