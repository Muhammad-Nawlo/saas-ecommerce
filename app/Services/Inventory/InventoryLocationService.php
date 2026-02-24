<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Modules\Shared\Domain\Exceptions\FeatureNotEnabledException;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Models\Inventory\InventoryLocation;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * CRUD locations. Enforces single location when multi_location_inventory is disabled.
 */
final class InventoryLocationService
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}
    private const DEFAULT_LOCATION_CODE = 'default';

    public function create(string $tenantId, string $name, string $code, string $type = InventoryLocation::TYPE_WAREHOUSE, ?array $address = null): InventoryLocation
    {
        $this->ensureMultiLocationOrFirst($tenantId);
        $code = Str::upper(Str::slug($code, '_'));
        if (InventoryLocation::forTenant($tenantId)->where('code', $code)->exists()) {
            throw new InvalidArgumentException("Location code already exists: {$code}");
        }
        $location = InventoryLocation::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'address' => $address,
            'is_active' => true,
        ]);
        $this->auditLogger->logTenantAction('location_created', 'Location created: ' . $location->code, $location, ['actor_id' => auth()->id()]);
        return $location;
    }

    public function getDefaultLocation(string $tenantId): InventoryLocation
    {
        $location = InventoryLocation::forTenant($tenantId)->active()->first();
        if ($location !== null) {
            return $location;
        }
        return InventoryLocation::create([
            'tenant_id' => $tenantId,
            'name' => 'Default',
            'code' => self::DEFAULT_LOCATION_CODE,
            'type' => InventoryLocation::TYPE_WAREHOUSE,
            'address' => null,
            'is_active' => true,
        ]);
    }

    public function getOrCreateDefaultLocation(string $tenantId): InventoryLocation
    {
        $location = InventoryLocation::forTenant($tenantId)->where('code', self::DEFAULT_LOCATION_CODE)->first();
        if ($location !== null) {
            return $location;
        }
        return $this->getDefaultLocation($tenantId);
    }

    public function deactivate(InventoryLocation $location): void
    {
        $location->update(['is_active' => false]);
    }

    public function activate(InventoryLocation $location): void
    {
        $location->update(['is_active' => true]);
    }

    public function canCreateMoreLocations(string $tenantId): bool
    {
        if (function_exists('tenant_feature') && !tenant_feature('multi_location_inventory')) {
            return InventoryLocation::forTenant($tenantId)->count() === 0;
        }
        return true;
    }

    public function canTransfer(string $tenantId): bool
    {
        return function_exists('tenant_feature') && (bool) tenant_feature('multi_location_inventory');
    }

    private function ensureMultiLocationOrFirst(string $tenantId): void
    {
        if (!$this->canCreateMoreLocations($tenantId)) {
            throw FeatureNotEnabledException::forFeature('multi_location_inventory');
        }
    }
}
