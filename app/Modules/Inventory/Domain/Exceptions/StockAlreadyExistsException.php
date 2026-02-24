<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class StockAlreadyExistsException extends DomainException
{
    public static function forProduct(string $productId): self
    {
        return new self(sprintf('Stock already exists for product %s', $productId));
    }
}
