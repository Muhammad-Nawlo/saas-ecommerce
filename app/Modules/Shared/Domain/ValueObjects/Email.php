<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class Email
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

    public function domain(): string
    {
        $parts = explode('@', $this->value);
        return $parts[1] ?? '';
    }

    public function localPart(): string
    {
        $parts = explode('@', $this->value);
        return $parts[0] ?? '';
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
