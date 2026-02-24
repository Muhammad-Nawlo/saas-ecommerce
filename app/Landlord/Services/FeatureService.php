<?php

declare(strict_types=1);

namespace App\Landlord\Services;

use App\Landlord\Models\Feature;
use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use Illuminate\Support\Facades\Cache;

final class FeatureService
{
    private const string CACHE_PREFIX = 'tenant:';
    private const string CACHE_SUFFIX = ':features';
    private const int CACHE_TTL = 600;

    public function hasFeature(string $tenantId, string $featureCode): bool
    {
        $value = $this->getFeatureValue($tenantId, $featureCode);
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            $int = (int) $value;
            return $int > 0 || $int === -1;
        }
        return (bool) $value;
    }

    /**
     * Returns null for unlimited (-1).
     */
    public function getFeatureLimit(string $tenantId, string $featureCode): ?int
    {
        $value = $this->getFeatureValue($tenantId, $featureCode);
        if (!is_numeric($value)) {
            return null;
        }
        $int = (int) $value;
        return $int === -1 ? null : $int;
    }

    /**
     * @return string|int|bool
     */
    private function getFeatureValue(string $tenantId, string $featureCode): string|int|bool
    {
        $features = $this->getCachedFeatures($tenantId);
        if (!isset($features[$featureCode])) {
            return false;
        }
        $value = $features[$featureCode]['value'];
        $type = $features[$featureCode]['type'] ?? 'limit';
        if ($type === 'boolean') {
            return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
        }
        if ($type === 'limit') {
            return (int) $value;
        }
        return $value;
    }

    /**
     * @return array<string, array{value: string, type: string}>
     */
    private function getCachedFeatures(string $tenantId): array
    {
        $key = self::CACHE_PREFIX . $tenantId . self::CACHE_SUFFIX;
        return Cache::remember($key, self::CACHE_TTL, fn () => $this->loadFeatures($tenantId));
    }

    /**
     * @return array<string, array{value: string, type: string}>
     */
    private function loadFeatures(string $tenantId): array
    {
        $conn = config('tenancy.database.central_connection', config('database.default'));
        $sub = Subscription::on($conn)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByDesc('created_at')
            ->first();
        if ($sub === null) {
            return [];
        }
        $plan = Plan::on($conn)->with('planFeatures.feature')->find($sub->plan_id);
        if ($plan === null) {
            return [];
        }
        $out = [];
        foreach ($plan->planFeatures as $pf) {
            if ($pf->feature !== null) {
                $out[$pf->feature->code] = [
                    'value' => $pf->value,
                    'type' => $pf->feature->type,
                ];
            }
        }
        return $out;
    }

    public function invalidateCache(string $tenantId): void
    {
        Cache::forget(self::CACHE_PREFIX . $tenantId . self::CACHE_SUFFIX);
    }
}
