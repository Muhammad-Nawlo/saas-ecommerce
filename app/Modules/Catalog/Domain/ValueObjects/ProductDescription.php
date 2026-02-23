<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\ValueObjects;

final readonly class ProductDescription
{
    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function equals(ProductDescription $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
