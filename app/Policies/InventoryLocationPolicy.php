<?php

declare(strict_types=1);

namespace App\Policies;

use App\Constants\TenantPermissions;
use App\Models\Inventory\InventoryLocation;
use App\Models\User;

class InventoryLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::VIEW_INVENTORY);
    }

    public function view(User $user, InventoryLocation $location): bool
    {
        return tenant('id') !== null
            && $location->tenant_id === tenant('id')
            && $user->can(TenantPermissions::VIEW_INVENTORY);
    }

    public function create(User $user): bool
    {
        return tenant('id') !== null
            && $user->can(TenantPermissions::EDIT_INVENTORY)
            && app(\App\Services\Inventory\InventoryLocationService::class)->canCreateMoreLocations((string) tenant('id'));
    }

    public function update(User $user, InventoryLocation $location): bool
    {
        return tenant('id') !== null
            && $location->tenant_id === tenant('id')
            && $user->can(TenantPermissions::EDIT_INVENTORY);
    }

    public function delete(User $user, InventoryLocation $location): bool
    {
        return $this->update($user, $location);
    }
}
