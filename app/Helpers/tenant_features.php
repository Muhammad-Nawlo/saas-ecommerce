<?php

declare(strict_types=1);

use App\Landlord\Services\FeatureResolver;

if (!function_exists('tenant_feature')) {
    /**
     * Get feature value for the current tenant's plan.
     *
     * @return string|int|bool
     */
    function tenant_feature(string $code): string|int|bool
    {
        return app(FeatureResolver::class)->getFeatureValue($code);
    }
}

if (!function_exists('tenant_limit')) {
    /**
     * Get limit value for the current tenant's plan (-1 = unlimited returns null).
     */
    function tenant_limit(string $code): ?int
    {
        return app(FeatureResolver::class)->getLimit($code);
    }
}
