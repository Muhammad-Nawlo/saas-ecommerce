<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Services;

interface InventoryService
{
    public function reserve(string $productId, int $quantity): void;

    public function release(string $productId, int $quantity): void;

    public function getAvailableQuantity(string $productId): int;
}
