<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Services;

use App\Modules\Cart\Application\Services\StockValidationService;
use App\Modules\Orders\Application\Services\InventoryService;

final readonly class CartStockValidationService implements StockValidationService
{
    public function __construct(
        private InventoryService $inventoryService
    ) {
    }

    public function validateForItems(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            $available = $this->inventoryService->getAvailableQuantity($productId);
            if ($available < $quantity) {
                throw new \App\Modules\Shared\Domain\Exceptions\DomainException(
                    sprintf('Insufficient stock for product %s: required %d, available %d', $productId, $quantity, $available)
                );
            }
        }
    }
}
