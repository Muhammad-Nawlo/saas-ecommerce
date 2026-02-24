<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class PaymentProvider
{
    public const string STRIPE = 'stripe';
    public const string MANUAL = 'manual';
    public const string PAYPAL = 'paypal';

    private const VALID_VALUES = [
        self::STRIPE,
        self::MANUAL,
        self::PAYPAL,
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::VALID_VALUES, true)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid payment provider');
        }
        return new self($normalized);
    }

    public static function stripe(): self
    {
        return new self(self::STRIPE);
    }

    public static function manual(): self
    {
        return new self(self::MANUAL);
    }

    public static function paypal(): self
    {
        return new self(self::PAYPAL);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(PaymentProvider $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
