<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class StockValidationException extends DomainException
{
    public static function insufficientStock(string $productId, int $required, int $available): self
    {
        return new self(
            "Insufficient stock for product {$productId}: required {$required}, available {$available}"
        );
    }
}
