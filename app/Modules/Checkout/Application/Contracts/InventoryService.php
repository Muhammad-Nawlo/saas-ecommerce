<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Contracts;

interface InventoryService
{
    /**
     * @param array<int, array{product_id: string, quantity: int}> $items
     */
    public function validateStock(array $items): void;

    /**
     * @param array<int, array{product_id: string, quantity: int}> $items
     */
    public function reserveStock(array $items): void;

    /**
     * @param array<int, array{product_id: string, quantity: int}> $items
     */
    public function releaseStock(array $items): void;
}
