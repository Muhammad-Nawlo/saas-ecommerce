<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Contracts\Currency\RateProviderInterface;
use App\Models\Currency\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stub API rate provider. Configure base URL and key in config (e.g. config('currency.api_url'), config('currency.api_key')).
 * Returns empty array until integrated with a real API (e.g. exchangerate.host, openexchangerates).
 */
final class ApiRateProvider implements RateProviderInterface
{
    public function __construct(
        private ?string $apiUrl = null,
        private ?string $apiKey = null,
    ) {
        $this->apiUrl = $apiUrl ?? config('currency.api_url');
        $this->apiKey = $apiKey ?? config('currency.api_key');
    }

    public function fetchRates(Currency $baseCurrency): array
    {
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            Log::debug('Currency API not configured; skipping rate fetch.');
            return [];
        }
        try {
            $response = Http::timeout(10)->get($this->apiUrl, [
                'base' => $baseCurrency->code,
                'access_key' => $this->apiKey,
            ]);
            if (!$response->successful()) {
                Log::warning('Currency API request failed', ['status' => $response->status()]);
                return [];
            }
            $body = $response->json();
            $rates = $body['rates'] ?? $body['quotes'] ?? [];
            $currencyIdByCode = Currency::whereIn('code', array_keys($rates))->pluck('id', 'code')->all();
            $result = [];
            foreach ($rates as $code => $rate) {
                if (isset($currencyIdByCode[$code])) {
                    $result[$currencyIdByCode[$code]] = (float) $rate;
                }
            }
            return $result;
        } catch (\Throwable $e) {
            Log::warning('Currency API error: ' . $e->getMessage());
            return [];
        }
    }

    public function getProviderName(): string
    {
        return 'api';
    }
}
