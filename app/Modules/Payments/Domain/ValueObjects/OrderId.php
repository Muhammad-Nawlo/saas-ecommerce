<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use App\Modules\Shared\Domain\ValueObjects\Uuid;

final readonly class OrderId
{
    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $uuid = Uuid::fromString($value);
        return new self($uuid->value());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(OrderId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
