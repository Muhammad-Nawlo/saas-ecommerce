<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

/**
 * Thrown when monetary operations are attempted across different currencies.
 */
final class CurrencyMismatchException extends DomainException
{
    public static function forCurrencies(string $from, string $to): self
    {
        return new self(sprintf('Currency mismatch: cannot operate on %s and %s', $from, $to));
    }
}
