<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

/**
 * Money (Value Object)
 *
 * Single canonical money value object. Amount in minor units (cents) only; float is forbidden for monetary math.
 * All arithmetic (add, subtract) enforces same currency; throws CurrencyMismatchException on mismatch.
 * Used by Payments, Checkout, InvoiceService, Financial flows. Immutable (readonly).
 *
 * No side effects. No tenant or DB; stateless.
 */
final readonly class Money
{
    private function __construct(
        private int $amount,
        private string $currency,
    ) {
    }

    /**
     * Create Money from minor units (cents) and ISO 4217 3-letter currency code.
     *
     * @param int $amount Amount in minor units (e.g. cents).
     * @param string $currency 3-letter currency code (e.g. USD); normalized to uppercase.
     * @return self
     * @throws InvalidValueObject When currency length is not 3.
     */
    public static function fromMinorUnits(int $amount, string $currency): self
    {
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            throw InvalidValueObject::forValue(self::class, $currency, 'Currency must be a 3-letter ISO 4217 code');
        }
        return new self($amount, $currency);
    }

    public function getMinorUnits(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /** @deprecated Use getMinorUnits() */
    public function amountInMinorUnits(): int
    {
        return $this->amount;
    }

    /** @deprecated Use getCurrency() */
    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    /** @return array{amount: int, currency: string} */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    public function __toString(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . $this->currency;
    }

    /** Human-readable format (same as __toString). */
    public function format(): string
    {
        return $this->__toString();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::forCurrencies($this->currency, $other->currency);
        }
    }
}
