<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class PaymentStatus
{
    public const string PENDING = 'pending';
    public const string AUTHORIZED = 'authorized';
    public const string SUCCEEDED = 'succeeded';
    public const string FAILED = 'failed';
    public const string REFUNDED = 'refunded';
    public const string CANCELLED = 'cancelled';

    private const VALID_VALUES = [
        self::PENDING,
        self::AUTHORIZED,
        self::SUCCEEDED,
        self::FAILED,
        self::REFUNDED,
        self::CANCELLED,
    ];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::PENDING => [self::AUTHORIZED, self::CANCELLED],
        self::AUTHORIZED => [self::SUCCEEDED, self::FAILED],
        self::SUCCEEDED => [self::REFUNDED],
        self::FAILED => [],
        self::REFUNDED => [],
        self::CANCELLED => [],
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::VALID_VALUES, true)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid payment status');
        }
        return new self($normalized);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function authorized(): self
    {
        return new self(self::AUTHORIZED);
    }

    public static function succeeded(): self
    {
        return new self(self::SUCCEEDED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function refunded(): self
    {
        return new self(self::REFUNDED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function canTransitionTo(PaymentStatus $target): bool
    {
        $allowed = self::TRANSITIONS[$this->value] ?? [];
        return in_array($target->value, $allowed, true);
    }

    public function equals(PaymentStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
