<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

final class BusinessRuleViolation extends DomainException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
