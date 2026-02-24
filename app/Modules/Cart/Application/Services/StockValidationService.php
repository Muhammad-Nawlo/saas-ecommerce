<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Services;

interface StockValidationService
{
    /**
     * @param array<int, array{product_id: string, quantity: int}> $items
     */
    public function validateForItems(array $items): void;
}
