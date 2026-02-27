<?php

declare(strict_types=1);

use App\Landlord\Services\FeatureResolver;

if (!function_exists('tenant_feature')) {
    /**
     * Get feature value for the current tenant's plan. Requires tenant context (e.g. tenant('id') set by InitializeTenancyByDomain).
     * Reads from central DB (Subscription, Plan, plan_features) via FeatureResolver; result cached per tenant.
     *
     * @param string $code Feature code (e.g. multi_location_inventory, products_limit).
     * @return string|int|bool Boolean features return true/false; limit features return int (-1 = unlimited); else string.
     */
    function tenant_feature(string $code): string|int|bool
    {
        return app(FeatureResolver::class)->getFeatureValue($code);
    }
}

if (!function_exists('tenant_limit')) {
    /**
     * Get limit value for the current tenant's plan. Requires tenant context. -1 (unlimited) is returned as null.
     *
     * @param string $code Feature code (e.g. products_limit).
     * @return int|null Limit value or null if unlimited / not numeric.
     */
    function tenant_limit(string $code): ?int
    {
        return app(FeatureResolver::class)->getLimit($code);
    }
}
