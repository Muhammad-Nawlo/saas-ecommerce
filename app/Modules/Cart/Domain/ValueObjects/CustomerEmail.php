<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class CustomerEmail
{
    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw InvalidValueObject::forValue(self::class, $value, 'Email cannot be empty');
        }
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid email format');
        }
        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
