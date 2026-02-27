<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Modules\Shared\Domain\ValueObjects\Money;
use InvalidArgumentException;

/**
 * CurrencyConversionService
 *
 * Converts Money to target currency using exchange rate (current or historical). Used when locking
 * financial orders (base/display currency), displaying amounts, and storing rate snapshots.
 * Rounding uses tenant's rounding_strategy (TenantCurrencySetting). All amounts in minor units;
 * convertWithSnapshot returns rate_snapshot for immutable storage (order/invoice). Assumes tenant
 * context for tenant settings. Does not write financial order/invoice; used by OrderCurrencySnapshotService.
 */
final class CurrencyConversionService
{
    public function __construct(
        private ExchangeRateService $rateService,
        private CurrencyService $currencyService,
    ) {}

    /**
     * Convert amount to target currency. If $at is null uses latest rate; otherwise historical rate.
     * Rounding uses tenant's rounding_strategy.
     */
    public function convert(Money $money, Currency $target, ?\DateTimeInterface $at = null): Money
    {
        $baseCurrency = Currency::where('code', $money->getCurrency())->first();
        if ($baseCurrency === null) {
            throw new InvalidArgumentException("Unknown currency: {$money->getCurrency()}");
        }
        if ($baseCurrency->id === $target->id) {
            return $money;
        }
        $rateRow = $at !== null
            ? $this->rateService->getRateAt($baseCurrency, $target, $at)
            : $this->rateService->getCurrentRate($baseCurrency, $target);
        if ($rateRow === null) {
            throw new InvalidArgumentException(
                "No exchange rate from {$money->getCurrency()} to {$target->code} at " . ($at ? $at->format('c') : 'now')
            );
        }
        $tenantId = tenant('id');
        $strategy = $tenantId !== null
            ? (app(CurrencyService::class)->getSettings((string) $tenantId)?->rounding_strategy ?? TenantCurrencySetting::ROUNDING_HALF_UP)
            : TenantCurrencySetting::ROUNDING_HALF_UP;
        $convertedMinor = $this->round($money->getMinorUnits() * $rateRow->rate, $strategy);
        return Money::fromMinorUnits((int) $convertedMinor, $target->code);
    }

    /**
     * Convert and return rate snapshot for storage (order/payment/invoice).
     *
     * @return array{converted: Money, rate_snapshot: array}
     */
    public function convertWithSnapshot(Money $money, Currency $target, ?\DateTimeInterface $at = null): array
    {
        $baseCurrency = Currency::where('code', $money->getCurrency())->first();
        if ($baseCurrency === null) {
            throw new InvalidArgumentException("Unknown currency: {$money->getCurrency()}");
        }
        if ($baseCurrency->id === $target->id) {
            $snapshot = [
                'base_currency_id' => $baseCurrency->id,
                'target_currency_id' => $target->id,
                'base_code' => $baseCurrency->code,
                'target_code' => $target->code,
                'rate' => 1.0,
                'source' => 'manual',
                'effective_at' => $at !== null
                    ? \Carbon\Carbon::parse($at)->toIso8601String()
                    : now()->toIso8601String(),
            ];
            return ['converted' => $money, 'rate_snapshot' => $snapshot];
        }
        $rateRow = $at !== null
            ? $this->rateService->getRateAt($baseCurrency, $target, $at)
            : $this->rateService->getCurrentRate($baseCurrency, $target);
        if ($rateRow === null) {
            throw new InvalidArgumentException("No exchange rate from {$money->getCurrency()} to {$target->code}");
        }
        $tenantId = tenant('id');
        $strategy = $tenantId !== null
            ? (app(CurrencyService::class)->getSettings((string) $tenantId)?->rounding_strategy ?? TenantCurrencySetting::ROUNDING_HALF_UP)
            : TenantCurrencySetting::ROUNDING_HALF_UP;
        $convertedMinor = $this->round($money->getMinorUnits() * $rateRow->rate, $strategy);
        return [
            'converted' => Money::fromMinorUnits((int) $convertedMinor, $target->code),
            'rate_snapshot' => $rateRow->toSnapshot(),
        ];
    }

    private function round(float $value, string $strategy): float
    {
        return match ($strategy) {
            TenantCurrencySetting::ROUNDING_BANKERS => $this->roundBankers($value),
            TenantCurrencySetting::ROUNDING_HALF_DOWN => round($value, 0, PHP_ROUND_HALF_DOWN),
            default => round($value, 0, PHP_ROUND_HALF_UP),
        };
    }

    private function roundBankers(float $value): float
    {
        $floor = floor($value);
        $frac = $value - $floor;
        if ($frac < 0.5) {
            return $floor;
        }
        if ($frac > 0.5) {
            return $floor + 1;
        }
        return ($floor % 2 === 0) ? $floor : $floor + 1;
    }
}
