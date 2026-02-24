<?php

declare(strict_types=1);

namespace App\Contracts\Currency;

use App\Models\Currency\Currency;

/**
 * Fetches exchange rates for a base currency. Do not hardcode API keys; use config/env.
 */
interface RateProviderInterface
{
    /**
     * Fetch current rates from base currency to other currencies.
     *
     * @return array<int, float> Map of target_currency_id => rate (e.g. 1 base = rate target)
     */
    public function fetchRates(Currency $baseCurrency): array;

    public function getProviderName(): string;
}
