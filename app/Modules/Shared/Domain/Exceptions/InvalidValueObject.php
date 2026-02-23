<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

final class InvalidValueObject extends DomainException
{
    public static function forValue(string $valueObjectName, mixed $value, string $reason = ''): self
    {
        $message = sprintf(
            'Invalid %s: %s%s',
            $valueObjectName,
            is_scalar($value) ? (string) $value : get_debug_type($value),
            $reason !== '' ? '. ' . $reason : ''
        );
        return new self($message);
    }
}
