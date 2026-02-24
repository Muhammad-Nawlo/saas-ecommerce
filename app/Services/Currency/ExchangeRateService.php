<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency\Currency;
use App\Models\Currency\ExchangeRate;
use App\Contracts\Currency\RateProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Get/set exchange rates. Always returns immutable rate snapshot (ExchangeRate model).
 */
final class ExchangeRateService
{
    public function __construct(
        private RateProviderInterface $rateProvider,
    ) {}

    public function getCurrentRate(Currency $base, Currency $target): ?ExchangeRate
    {
        if ($base->id === $target->id) {
            $rate = new ExchangeRate();
            $rate->base_currency_id = $base->id;
            $rate->target_currency_id = $target->id;
            $rate->rate = 1.0;
            $rate->source = ExchangeRate::SOURCE_MANUAL;
            $rate->effective_at = now();
            return $rate;
        }
        $key = "exchange_rate:{$base->id}:{$target->id}";
        $ttl = (int) config('currency.rate_cache_ttl', 3600);
        return Cache::remember($key, $ttl, function () use ($base, $target): ?ExchangeRate {
            $direct = ExchangeRate::where('base_currency_id', $base->id)
                ->where('target_currency_id', $target->id)
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->with(['baseCurrency', 'targetCurrency'])
                ->first();
            if ($direct !== null) {
                return $direct;
            }
            $reverse = ExchangeRate::where('base_currency_id', $target->id)
                ->where('target_currency_id', $base->id)
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->with(['baseCurrency', 'targetCurrency'])
                ->first();
            if ($reverse !== null) {
                $synthetic = new ExchangeRate();
                $synthetic->base_currency_id = $base->id;
                $synthetic->target_currency_id = $target->id;
                $synthetic->rate = 1.0 / $reverse->rate;
                $synthetic->source = $reverse->source;
                $synthetic->effective_at = $reverse->effective_at;
                $synthetic->setRelation('baseCurrency', $base);
                $synthetic->setRelation('targetCurrency', $target);
                return $synthetic;
            }
            return null;
        });
    }

    public function getRateAt(Currency $base, Currency $target, \DateTimeInterface $at): ?ExchangeRate
    {
        if ($base->id === $target->id) {
            $rate = new ExchangeRate();
            $rate->base_currency_id = $base->id;
            $rate->target_currency_id = $target->id;
            $rate->rate = 1.0;
            $rate->source = ExchangeRate::SOURCE_MANUAL;
            $rate->effective_at = Carbon::instance($at);
            return $rate;
        }
        $direct = ExchangeRate::where('base_currency_id', $base->id)
            ->where('target_currency_id', $target->id)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->with(['baseCurrency', 'targetCurrency'])
            ->first();
        if ($direct !== null) {
            return $direct;
        }
        $reverse = ExchangeRate::where('base_currency_id', $target->id)
            ->where('target_currency_id', $base->id)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->with(['baseCurrency', 'targetCurrency'])
            ->first();
        if ($reverse !== null) {
            $synthetic = new ExchangeRate();
            $synthetic->base_currency_id = $base->id;
            $synthetic->target_currency_id = $target->id;
            $synthetic->rate = 1.0 / $reverse->rate;
            $synthetic->source = $reverse->source;
            $synthetic->effective_at = $reverse->effective_at;
            $synthetic->setRelation('baseCurrency', $base);
            $synthetic->setRelation('targetCurrency', $target);
            return $synthetic;
        }
        return null;
    }

    public function setManualRate(Currency $base, Currency $target, float $rate, ?\DateTimeInterface $effectiveAt = null): ExchangeRate
    {
        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be positive.');
        }
        $effectiveAt = $effectiveAt ?? now();
        return DB::transaction(function () use ($base, $target, $rate, $effectiveAt): ExchangeRate {
            $effectiveAt = $effectiveAt instanceof \Carbon\Carbon
                ? $effectiveAt
                : \Carbon\Carbon::parse($effectiveAt);
            $existing = ExchangeRate::where('base_currency_id', $base->id)
                ->where('target_currency_id', $target->id)
                ->where('effective_at', $effectiveAt)
                ->first();
            if ($existing !== null) {
                $existing->update(['rate' => $rate, 'source' => ExchangeRate::SOURCE_MANUAL]);
                Cache::forget("exchange_rate:{$base->id}:{$target->id}");
                return $existing->fresh();
            }
            $record = ExchangeRate::create([
                'base_currency_id' => $base->id,
                'target_currency_id' => $target->id,
                'rate' => $rate,
                'source' => ExchangeRate::SOURCE_MANUAL,
                'effective_at' => $effectiveAt,
            ]);
            Cache::forget("exchange_rate:{$base->id}:{$target->id}");
            return $record->load(['baseCurrency', 'targetCurrency']);
        });
    }

    public function updateRatesFromProvider(?string $tenantId = null): int
    {
        $base = app(CurrencyService::class)->getTenantBaseCurrency($tenantId);
        $rates = $this->rateProvider->fetchRates($base);
        $count = 0;
        $now = now();
        foreach ($rates as $targetCurrencyId => $rate) {
            if ($rate <= 0) {
                continue;
            }
            $target = Currency::find($targetCurrencyId);
            if ($target === null || $target->id === $base->id) {
                continue;
            }
            ExchangeRate::updateOrCreate(
                [
                    'base_currency_id' => $base->id,
                    'target_currency_id' => $target->id,
                    'effective_at' => $now,
                ],
                [
                    'rate' => $rate,
                    'source' => ExchangeRate::SOURCE_API,
                ],
            );
            $count++;
            Cache::forget("exchange_rate:{$base->id}:{$target->id}");
        }
        return $count;
    }
}
