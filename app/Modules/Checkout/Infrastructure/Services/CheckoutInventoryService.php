<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Services;

use App\Modules\Checkout\Application\Contracts\InventoryService;
use App\Modules\Checkout\Application\Exceptions\StockValidationException;
use App\Modules\Inventory\Application\Services\InventoryStockService;

final readonly class CheckoutInventoryService implements InventoryService
{
    public function __construct(
        private InventoryStockService $inventoryStockService
    ) {
    }

    public function validateStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $available = $this->inventoryStockService->getAvailableQuantity($productId);
            if ($available < $quantity) {
                throw StockValidationException::insufficientStock($productId, $quantity, $available);
            }
        }
    }

    public function reserveStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $this->inventoryStockService->reserve($productId, $quantity);
        }
    }

    public function releaseStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $this->inventoryStockService->release($productId, $quantity);
        }
    }
}
