<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\ValueObjects;

use App\Modules\Shared\Domain\ValueObjects\Uuid;

final readonly class CartItemId
{
    private function __construct(
        private Uuid $uuid
    ) {
    }

    public static function fromUuid(Uuid $uuid): self
    {
        return new self($uuid);
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value));
    }

    public static function generate(): self
    {
        return new self(Uuid::v4());
    }

    public function value(): string
    {
        return $this->uuid->value();
    }

    public function equals(CartItemId $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }

    public function __toString(): string
    {
        return $this->uuid->value();
    }
}
