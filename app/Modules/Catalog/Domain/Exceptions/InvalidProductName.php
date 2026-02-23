<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class InvalidProductName extends DomainException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
