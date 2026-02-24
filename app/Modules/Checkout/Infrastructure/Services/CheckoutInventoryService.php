<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Services;

use App\Modules\Checkout\Application\Contracts\InventoryService;
use App\Modules\Checkout\Application\Exceptions\StockValidationException;
use App\Modules\Inventory\Application\Services\InventoryStockService;
use App\Services\Inventory\InventoryAllocationService;

final readonly class CheckoutInventoryService implements InventoryService
{
    public function __construct(
        private InventoryStockService $inventoryStockService,
        private InventoryAllocationService $allocationService,
    ) {
    }

    private function useMultiLocation(): bool
    {
        return function_exists('tenant_feature')
            && (bool) tenant_feature('multi_location_inventory');
    }

    public function validateStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $available = $this->useMultiLocation()
                ? $this->allocationService->getAvailableQuantity($productId)
                : $this->inventoryStockService->getAvailableQuantity($productId);
            if ($available < $quantity) {
                throw StockValidationException::insufficientStock($productId, $quantity, $available);
            }
        }
    }

    public function reserveStock(array $items): void
    {
        if ($this->useMultiLocation()) {
            return;
        }
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $this->inventoryStockService->reserve($productId, $quantity);
        }
    }

    public function releaseStock(array $items): void
    {
        if ($this->useMultiLocation()) {
            return;
        }
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $this->inventoryStockService->release($productId, $quantity);
        }
    }
}
