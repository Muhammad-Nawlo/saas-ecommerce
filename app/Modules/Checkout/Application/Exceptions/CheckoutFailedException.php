<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class CheckoutFailedException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
