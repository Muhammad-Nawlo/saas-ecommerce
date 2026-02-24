<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class Threshold
{
    private function __construct(
        private int $value
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw InvalidValueObject::forValue(self::class, (string) $value, 'Threshold cannot be negative');
        }
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(Threshold $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
