<?php

declare(strict_types=1);

namespace App\Services\Financial;

/**
 * Result of tax calculation for an order. All amounts in cents.
 */
final readonly class TaxResult
{
    /** @param list<array{name: string, percentage: float, taxable_amount_cents: int, tax_amount_cents: int}> $taxLines */
    public function __construct(
        public int $subtotal_cents,
        public int $tax_total_cents,
        public int $total_cents,
        public array $taxLines,
        public string $currency,
    ) {}
}
