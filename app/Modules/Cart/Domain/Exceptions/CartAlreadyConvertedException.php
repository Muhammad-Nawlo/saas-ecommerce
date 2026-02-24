<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class CartAlreadyConvertedException extends DomainException
{
    public static function forCart(string $cartId): self
    {
        return new self(sprintf('Cart %s has already been converted to an order', $cartId));
    }
}
