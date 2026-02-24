<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class StripeCustomerId
{
    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw InvalidValueObject::forValue(self::class, $value, 'Stripe customer ID cannot be empty');
        }
        if (!str_starts_with($trimmed, 'cus_')) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid Stripe customer ID format');
        }
        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
