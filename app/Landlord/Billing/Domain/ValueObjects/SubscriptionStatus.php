<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class SubscriptionStatus
{
    public const string INCOMPLETE = 'incomplete';
    public const string ACTIVE = 'active';
    public const string PAST_DUE = 'past_due';
    public const string CANCELLED = 'cancelled';
    public const string UNPAID = 'unpaid';
    public const string TRIALING = 'trialing';

    private const VALID_VALUES = [
        self::INCOMPLETE,
        self::ACTIVE,
        self::PAST_DUE,
        self::CANCELLED,
        self::UNPAID,
        self::TRIALING,
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::VALID_VALUES, true)) {
            throw InvalidValueObject::forValue(self::class, $value, 'Invalid subscription status');
        }
        return new self($normalized);
    }

    public static function incomplete(): self
    {
        return new self(self::INCOMPLETE);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function pastDue(): self
    {
        return new self(self::PAST_DUE);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function unpaid(): self
    {
        return new self(self::UNPAID);
    }

    public static function trialing(): self
    {
        return new self(self::TRIALING);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isIncomplete(): bool
    {
        return $this->value === self::INCOMPLETE;
    }

    public function isPastDue(): bool
    {
        return $this->value === self::PAST_DUE;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function isTrialing(): bool
    {
        return $this->value === self::TRIALING;
    }
}
