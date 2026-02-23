<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class TenantId
{
    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw InvalidValueObject::forValue(self::class, $value, 'Tenant ID cannot be empty');
        }
        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(TenantId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
