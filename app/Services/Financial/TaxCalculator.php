<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\TaxRate;

/**
 * Calculates tax for an order. Uses applicable tax rates; rounds at cent level.
 * Tax rates are typically filtered by tenant_id and country/region (caller's responsibility).
 */
final class TaxCalculator
{
    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, TaxRate>|array<TaxRate> $applicableRates
     */
    public function calculate(FinancialOrder $order, iterable $applicableRates = []): TaxResult
    {
        $subtotal_cents = 0;
        $taxLines = [];
        $currency = $order->currency;

        foreach ($order->items as $item) {
            $itemSubtotal = $item->quantity * $item->unit_price_cents;
            $subtotal_cents += $itemSubtotal;

            foreach ($applicableRates as $rate) {
                $taxable = $itemSubtotal;
                $percentage = (float) $rate->percentage;
                $tax_amount_cents = (int) round($taxable * $percentage / 100);
                if ($tax_amount_cents <= 0) {
                    continue;
                }
                $taxLines[] = [
                    'name' => $rate->name,
                    'percentage' => $percentage,
                    'taxable_amount_cents' => $taxable,
                    'tax_amount_cents' => $tax_amount_cents,
                ];
            }
        }

        $tax_total_cents = 0;
        foreach ($taxLines as $line) {
            $tax_total_cents += $line['tax_amount_cents'];
        }
        $total_cents = $subtotal_cents + $tax_total_cents;

        return new TaxResult(
            subtotal_cents: $subtotal_cents,
            tax_total_cents: $tax_total_cents,
            total_cents: $total_cents,
            taxLines: $taxLines,
            currency: $currency,
        );
    }
}
