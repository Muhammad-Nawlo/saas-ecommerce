<?php

declare(strict_types=1);

namespace App\Landlord\Services;

use App\Landlord\Services\FeatureResolver;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Shared\Domain\Exceptions\NoActiveSubscriptionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Tenancy;

/**
 * Tracks feature consumption per tenant (products count, inventory locations, etc.) vs plan limits.
 * Run in tenant context or pass tenant and resolve via Tenancy.
 */
final class FeatureUsageService
{
    private const CACHE_TTL = 120;

    public function __construct(
        private FeatureResolver $featureResolver,
        private ?Tenancy $tenancy = null
    ) {
    }

    /**
     * Current tenant: products count vs products_limit. Returns ['used' => n, 'limit' => n|null, 'at_limit' => bool].
     *
     * @return array{used: int, limit: int|null, at_limit: bool}
     */
    public function productsUsage(): array
    {
        $tenantId = (string) tenant('id');
        $key = tenant_cache_key('feature_usage:products', $tenantId);
        $used = (int) Cache::remember($key, self::CACHE_TTL, fn () => ProductModel::count());
        $limit = null;
        try {
            $limit = $this->featureResolver->getLimit('products_limit');
        } catch (NoActiveSubscriptionException) {
        }
        return [
            'used' => $used,
            'limit' => $limit,
            'at_limit' => $limit !== null && $used >= $limit,
        ];
    }

    /**
     * Inventory locations count vs multi_location_inventory (feature) and implicit limit.
     *
     * @return array{used: int, feature_enabled: bool, at_limit: bool}
     */
    public function inventoryLocationsUsage(): array
    {
        $tenantId = (string) tenant('id');
        $key = tenant_cache_key('feature_usage:inventory_locations', $tenantId);
        $used = (int) Cache::remember($key, self::CACHE_TTL, function (): int {
            if (!\Schema::hasTable('inventory_locations')) {
                return 0;
            }
            return (int) DB::table('inventory_locations')->count();
        });
        $featureEnabled = false;
        try {
            $featureEnabled = (bool) $this->featureResolver->getFeatureValue('multi_location_inventory');
        } catch (NoActiveSubscriptionException) {
        }
        $atLimit = !$featureEnabled && $used > 1;
        return [
            'used' => $used,
            'feature_enabled' => $featureEnabled,
            'at_limit' => $atLimit,
        ];
    }

    /**
     * Summary for current tenant: usage vs limits for display in tenant panel.
     *
     * @return array{products: array{used: int, limit: int|null, at_limit: bool}, inventory_locations: array{used: int, feature_enabled: bool, at_limit: bool}}
     */
    public function usageSummary(): array
    {
        return [
            'products' => $this->productsUsage(),
            'inventory_locations' => $this->inventoryLocationsUsage(),
        ];
    }
}
