<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Exceptions;

use App\Modules\Shared\Domain\Exceptions\DomainException;

final class InvalidCartStateException extends DomainException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
