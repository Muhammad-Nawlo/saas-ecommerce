<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency\Currency;
use App\Models\Financial\FinancialOrder;
use App\ValueObjects\Money;

/**
 * Fills order currency snapshot (base_currency, display_currency, exchange_rate_snapshot, base/display amounts).
 * Call when locking the order so rates are immutable thereafter.
 */
final class OrderCurrencySnapshotService
{
    public function __construct(
        private CurrencyConversionService $conversionService,
        private CurrencyService $currencyService,
    ) {}

    /**
     * Fill base/display currency and rate snapshot on the order. Idempotent if already set.
     */
    public function fillSnapshot(FinancialOrder $order): void
    {
        if ($order->exchange_rate_snapshot !== null && $order->base_currency !== null) {
            return;
        }
        $baseCurrency = $this->currencyService->getTenantBaseCurrency($order->tenant_id);
        $displayCurrency = Currency::where('code', $order->currency)->first() ?? $baseCurrency;
        $order->base_currency = $baseCurrency->code;
        $order->display_currency = $displayCurrency->code;
        $order->subtotal_base_cents = $order->subtotal_cents;
        $order->subtotal_display_cents = $order->subtotal_cents;
        $order->tax_base_cents = $order->tax_total_cents;
        $order->tax_display_cents = $order->tax_total_cents;
        $order->total_base_cents = $order->total_cents;
        $order->total_display_cents = $order->total_cents;
        $order->exchange_rate_snapshot = null;
        if ($baseCurrency->id !== $displayCurrency->id) {
            $subtotalMoney = Money::fromCents($order->subtotal_cents, $order->currency);
            $taxMoney = Money::fromCents($order->tax_total_cents, $order->currency);
            $totalMoney = Money::fromCents($order->total_cents, $order->currency);
            $subResult = $this->conversionService->convertWithSnapshot($subtotalMoney, $baseCurrency);
            $taxResult = $this->conversionService->convertWithSnapshot($taxMoney, $baseCurrency);
            $totalResult = $this->conversionService->convertWithSnapshot($totalMoney, $baseCurrency);
            $order->subtotal_base_cents = $subResult['converted']->amount;
            $order->tax_base_cents = $taxResult['converted']->amount;
            $order->total_base_cents = $totalResult['converted']->amount;
            $order->exchange_rate_snapshot = $totalResult['rate_snapshot'];
        } else {
            $order->exchange_rate_snapshot = [
                'base_currency_id' => $baseCurrency->id,
                'target_currency_id' => $displayCurrency->id,
                'base_code' => $baseCurrency->code,
                'target_code' => $displayCurrency->code,
                'rate' => 1.0,
                'source' => 'manual',
                'effective_at' => now()->toIso8601String(),
            ];
        }
        $order->saveQuietly();
    }
}
