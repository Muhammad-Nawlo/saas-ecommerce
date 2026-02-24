<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class CartStatus
{
    public const string ACTIVE = 'active';
    public const string CONVERTED = 'converted';
    public const string ABANDONED = 'abandoned';

    private const VALID_VALUES = [
        self::ACTIVE,
        self::CONVERTED,
        self::ABANDONED,
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::VALID_VALUES, true)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid cart status');
        }
        return new self($normalized);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function converted(): self
    {
        return new self(self::CONVERTED);
    }

    public static function abandoned(): self
    {
        return new self(self::ABANDONED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(CartStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isConverted(): bool
    {
        return $this->value === self::CONVERTED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
