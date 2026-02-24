<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable money value object. Amount stored in cents (integer).
 * Currency: ISO 4217 (3-letter). All operations throw on currency mismatch.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        string $currency,
    ) {
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO 4217 code.');
        }
        $this->currency = $currency;
    }

    public string $currency;

    public static function fromCents(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function format(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . $this->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                'Currency mismatch: ' . $this->currency . ' vs ' . $other->currency
            );
        }
    }
}
