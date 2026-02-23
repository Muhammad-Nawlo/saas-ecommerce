<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\ValueObjects;

use App\Modules\Catalog\Domain\Exceptions\InvalidProductName;

final readonly class ProductName
{
    private const MAX_LENGTH = 255;

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw InvalidProductName::because('Product name cannot be empty');
        }
        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidProductName::because(sprintf('Product name cannot exceed %d characters', self::MAX_LENGTH));
        }
        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ProductName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
