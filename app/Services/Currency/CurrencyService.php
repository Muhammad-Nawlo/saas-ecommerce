<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Models\Currency\TenantEnabledCurrency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Modules\Shared\Domain\Exceptions\FeatureNotEnabledException;
use InvalidArgumentException;

final class CurrencyService
{
    public function getTenantBaseCurrency(?string $tenantId = null): Currency
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $setting = TenantCurrencySetting::where('tenant_id', $tenantId)->first();
        if ($setting === null) {
            $base = Currency::where('code', 'USD')->first();
            if ($base === null) {
                $base = Currency::first();
            }
            if ($base === null) {
                throw new InvalidArgumentException('No currencies found. Run CurrencySeeder in tenant context.');
            }
            TenantCurrencySetting::create([
                'tenant_id' => $tenantId,
                'base_currency_id' => $base->id,
                'allow_multi_currency' => false,
                'rounding_strategy' => TenantCurrencySetting::ROUNDING_HALF_UP,
            ]);
            return $base;
        }
        return $setting->baseCurrency;
    }

    /**
     * @return Collection<int, Currency>
     */
    public function listEnabledCurrencies(?string $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $setting = TenantCurrencySetting::where('tenant_id', $tenantId)->first();
        if ($setting === null || !$setting->allow_multi_currency) {
            return collect([$this->getTenantBaseCurrency($tenantId)]);
        }
        $ids = TenantEnabledCurrency::where('tenant_id', $tenantId)->pluck('currency_id');
        $currencies = Currency::whereIn('id', $ids)->where('is_active', true)->orderBy('code')->get();
        if ($currencies->isEmpty()) {
            return collect([$this->getTenantBaseCurrency($tenantId)]);
        }
        return $currencies;
    }

    public function enableCurrency(int $currencyId, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $this->ensureMultiCurrencyAllowed($tenantId);
        TenantEnabledCurrency::firstOrCreate(
            ['tenant_id' => $tenantId, 'currency_id' => $currencyId],
            ['tenant_id' => $tenantId, 'currency_id' => $currencyId],
        );
    }

    public function disableCurrency(int $currencyId, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        TenantEnabledCurrency::where('tenant_id', $tenantId)->where('currency_id', $currencyId)->delete();
    }

    public function getSettings(?string $tenantId = null): ?TenantCurrencySetting
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $key = 'tenant_currency_settings:' . $tenantId;
        $ttl = (int) config('currency.settings_cache_ttl', 300);
        return Cache::remember($key, $ttl, function () use ($tenantId): ?TenantCurrencySetting {
            return TenantCurrencySetting::where('tenant_id', $tenantId)->with('baseCurrency')->first();
        });
    }

    private function ensureMultiCurrencyAllowed(string $tenantId): void
    {
        if (!function_exists('tenant_feature') || !(bool) tenant_feature('multi_currency')) {
            throw FeatureNotEnabledException::forFeature('multi_currency');
        }
        $s = TenantCurrencySetting::where('tenant_id', $tenantId)->first();
        if ($s !== null && !$s->allow_multi_currency) {
            throw new InvalidArgumentException('Tenant has not enabled multi-currency in settings.');
        }
    }
}
