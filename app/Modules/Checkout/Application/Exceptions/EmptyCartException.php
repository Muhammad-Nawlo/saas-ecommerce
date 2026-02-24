<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class EmptyCartException extends DomainException
{
    public static function forCart(string $cartId): self
    {
        return new self("Cart {$cartId} is empty and cannot be checked out");
    }
}
