<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class BillingInterval
{
    public const string MONTHLY = 'monthly';
    public const string YEARLY = 'yearly';

    private const VALID_VALUES = [self::MONTHLY, self::YEARLY];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::VALID_VALUES, true)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Billing interval must be monthly or yearly');
        }
        return new self($normalized);
    }

    public static function monthly(): self
    {
        return new self(self::MONTHLY);
    }

    public static function yearly(): self
    {
        return new self(self::YEARLY);
    }

    public function value(): string
    {
        return $this->value;
    }
}
