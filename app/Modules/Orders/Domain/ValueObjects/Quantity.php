<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class Quantity
{
    private function __construct(
        private int $value
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw InvalidValueObject::forValue(self::class, (string) $value, 'Quantity must be positive');
        }
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(Quantity $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
