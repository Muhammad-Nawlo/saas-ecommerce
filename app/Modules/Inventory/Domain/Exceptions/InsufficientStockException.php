<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    public static function forQuantity(int $requested, int $available): self
    {
        return new self(
            sprintf('Insufficient stock: requested %d, available %d', $requested, $available)
        );
    }
}
