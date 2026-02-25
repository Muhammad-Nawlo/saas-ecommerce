<?php

declare(strict_types=1);

if (! function_exists('tenant_cache_key')) {
    /**
     * Build a cache key with tenant prefix to avoid cross-tenant bleed.
     * Use for any tenant-scoped cache when running in landlord context or when
     * the cache store is shared (e.g. Redis cluster). In tenant context,
     * Stancl's CacheTenancyBootstrapper may also prefix; this helper guarantees
     * tenant isolation when tenantId is passed explicitly.
     *
     * @param  string  $key  Cache key (e.g. "features", "currency:USD").
     * @param  string|null  $tenantId  Tenant ID; if null, uses tenant('id') when in tenant context.
     * @return string Key prefixed with "tenant:{id}:" when tenant is known, otherwise unchanged.
     */
    function tenant_cache_key(string $key, ?string $tenantId = null): string
    {
        $id = $tenantId ?? (function_exists('tenant') && tenant() !== null ? (string) tenant('id') : null);
        if ($id === null || $id === '') {
            return $key;
        }
        return 'tenant:' . $id . ':' . $key;
    }
}
