<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Services;

use App\Modules\Inventory\Application\Services\InventoryStockService;
use App\Modules\Orders\Application\Services\InventoryService;

final readonly class LaravelInventoryService implements InventoryService
{
    public function __construct(
        private InventoryStockService $inventoryStockService
    ) {
    }

    public function reserve(string $productId, int $quantity): void
    {
        $this->inventoryStockService->reserve($productId, $quantity);
    }

    public function release(string $productId, int $quantity): void
    {
        $this->inventoryStockService->release($productId, $quantity);
    }

    public function getAvailableQuantity(string $productId): int
    {
        return $this->inventoryStockService->getAvailableQuantity($productId);
    }
}
