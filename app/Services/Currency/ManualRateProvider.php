<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Contracts\Currency\RateProviderInterface;
use App\Models\Currency\Currency;

/**
 * Manual rates only. Does not fetch from API; used when no external provider is configured.
 */
final class ManualRateProvider implements RateProviderInterface
{
    public function fetchRates(Currency $baseCurrency): array
    {
        return [];
    }

    public function getProviderName(): string
    {
        return 'manual';
    }
}
