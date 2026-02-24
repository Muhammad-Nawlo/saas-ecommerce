<?php

declare(strict_types=1);

namespace App\Landlord\Services;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Modules\Shared\Domain\Exceptions\NoActiveSubscriptionException;
use Illuminate\Support\Facades\Cache;

final class FeatureResolver
{
    private const string CACHE_KEY_PREFIX = 'tenant:';
    private const string CACHE_KEY_SUFFIX = ':features';
    private const int CACHE_TTL_SECONDS = 600;

    public function getCurrentTenantSubscription(): Subscription
    {
        $tenantId = $this->resolveTenantId();
        $subscription = $this->getSubscriptionForTenant($tenantId);
        if ($subscription === null || !$subscription->isActive()) {
            throw NoActiveSubscriptionException::forTenant($tenantId);
        }
        return $subscription;
    }

    public function getPlan(): Plan
    {
        $subscription = $this->getCurrentTenantSubscription();
        $plan = $subscription->plan;
        if ($plan === null) {
            throw NoActiveSubscriptionException::forTenant($subscription->tenant_id);
        }
        return $plan;
    }

    /**
     * @return string|int|bool
     */
    public function getFeatureValue(string $featureCode): string|int|bool
    {
        $features = $this->getCachedFeatures();
        if (!isset($features[$featureCode])) {
            return false;
        }
        $value = $features[$featureCode];
        $feature = $this->getFeatureByCode($featureCode);
        if ($feature !== null && $feature->isBoolean()) {
            return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
        }
        if ($feature !== null && $feature->isLimit()) {
            return (int) $value;
        }
        return $value;
    }

    public function hasFeature(string $featureCode): bool
    {
        $value = $this->getFeatureValue($featureCode);
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value > 0 || (int) $value === -1;
        }
        return (bool) $value;
    }

    /**
     * -1 means unlimited, returns null for unlimited.
     */
    public function getLimit(string $featureCode): ?int
    {
        $value = $this->getFeatureValue($featureCode);
        if (!is_numeric($value)) {
            return null;
        }
        $int = (int) $value;
        if ($int === -1) {
            return null;
        }
        return $int;
    }

    public function invalidateCacheForTenant(string $tenantId): void
    {
        Cache::forget($this->cacheKey($tenantId));
    }

    public function invalidateCurrentTenantCache(): void
    {
        $tenantId = $this->resolveTenantId();
        $this->invalidateCacheForTenant($tenantId);
    }

    /**
     * @return array<string, string>
     */
    private function getCachedFeatures(): array
    {
        $tenantId = $this->resolveTenantId();
        return Cache::remember(
            $this->cacheKey($tenantId),
            self::CACHE_TTL_SECONDS,
            fn () => $this->loadFeaturesForTenant($tenantId)
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadFeaturesForTenant(string $tenantId): array
    {
        $subscription = $this->getSubscriptionForTenant($tenantId);
        if ($subscription === null || !$subscription->isActive()) {
            return [];
        }
        $plan = $subscription->plan;
        if ($plan === null) {
            return [];
        }
        $plan->load('planFeatures.feature');
        $features = [];
        foreach ($plan->planFeatures as $pf) {
            if ($pf->feature !== null) {
                $features[$pf->feature->code] = $pf->value;
            }
        }
        return $features;
    }

    private function getSubscriptionForTenant(string $tenantId): ?Subscription
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        return Subscription::on($connection)
            ->where('tenant_id', $tenantId)
            ->with('plan')
            ->orderByDesc('created_at')
            ->first();
    }

    private function resolveTenantId(): string
    {
        $id = tenant('id');
        if ($id === null || $id === '') {
            throw NoActiveSubscriptionException::forTenant('unknown');
        }
        return (string) $id;
    }

    private function cacheKey(string $tenantId): string
    {
        return self::CACHE_KEY_PREFIX . $tenantId . self::CACHE_KEY_SUFFIX;
    }

    private function getFeatureByCode(string $code): ?\App\Landlord\Models\Feature
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        return \App\Landlord\Models\Feature::on($connection)->where('code', $code)->first();
    }
}
