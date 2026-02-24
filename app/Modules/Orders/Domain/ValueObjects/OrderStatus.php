<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class OrderStatus
{
    public const string PENDING = 'pending';
    public const string CONFIRMED = 'confirmed';
    public const string PAID = 'paid';
    public const string CANCELLED = 'cancelled';
    public const string SHIPPED = 'shipped';

    private const VALID_VALUES = [
        self::PENDING,
        self::CONFIRMED,
        self::PAID,
        self::CANCELLED,
        self::SHIPPED,
    ];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::PENDING => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::PAID, self::CANCELLED],
        self::PAID => [self::SHIPPED],
        self::CANCELLED => [],
        self::SHIPPED => [],
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::VALID_VALUES, true)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid order status');
        }
        return new self($normalized);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function confirmed(): self
    {
        return new self(self::CONFIRMED);
    }

    public static function paid(): self
    {
        return new self(self::PAID);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function shipped(): self
    {
        return new self(self::SHIPPED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function canTransitionTo(OrderStatus $target): bool
    {
        $allowed = self::TRANSITIONS[$this->value] ?? [];
        return in_array($target->value, $allowed, true);
    }

    public function equals(OrderStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->value === self::CONFIRMED;
    }

    public function isPaid(): bool
    {
        return $this->value === self::PAID;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function isShipped(): bool
    {
        return $this->value === self::SHIPPED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
