<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class Money
{
    private function __construct(
        private int $amountInMinorUnits,
        private string $currency
    ) {
    }

    public static function fromMinorUnits(int $amountInMinorUnits, string $currency): self
    {
        if (strlen(trim($currency)) !== 3) {
            throw InvalidValueObject::forValue(self::class, $currency, 'Currency must be a 3-letter ISO 4217 code');
        }
        return new self($amountInMinorUnits, strtoupper(trim($currency)));
    }

    public static function fromMajorUnits(float $amount, string $currency): self
    {
        if (strlen(trim($currency)) !== 3) {
            throw InvalidValueObject::forValue(self::class, $currency, 'Currency must be a 3-letter ISO 4217 code');
        }
        $minor = (int) round($amount * 100);
        return new self($minor, strtoupper(trim($currency)));
    }

    public function amountInMinorUnits(): int
    {
        return $this->amountInMinorUnits;
    }

    public function amountInMajorUnits(): float
    {
        return $this->amountInMinorUnits / 100;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInMinorUnits + $other->amountInMinorUnits, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInMinorUnits - $other->amountInMinorUnits, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->amountInMinorUnits === $other->amountInMinorUnits;
    }

    public function __toString(): string
    {
        return number_format($this->amountInMajorUnits(), 2) . ' ' . $this->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidValueObject::forValue(
                self::class,
                $other->currency,
                'Cannot operate on different currencies: ' . $this->currency . ' vs ' . $other->currency
            );
        }
    }
}
