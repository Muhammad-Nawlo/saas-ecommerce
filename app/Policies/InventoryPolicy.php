<?php

declare(strict_types=1);

namespace App\Policies;

use App\Constants\TenantPermissions;
use App\Models\User;
use App\Modules\Inventory\Infrastructure\Persistence\StockItemModel;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::VIEW_INVENTORY);
    }

    public function view(User $user, StockItemModel $stock): bool
    {
        return tenant('id') !== null
            && $stock->tenant_id === tenant('id')
            && $user->can(TenantPermissions::VIEW_INVENTORY);
    }

    public function create(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::EDIT_INVENTORY);
    }

    public function update(User $user, StockItemModel $stock): bool
    {
        return tenant('id') !== null
            && $stock->tenant_id === tenant('id')
            && $user->can(TenantPermissions::EDIT_INVENTORY);
    }

    public function delete(User $user, StockItemModel $stock): bool
    {
        return tenant('id') !== null
            && $stock->tenant_id === tenant('id')
            && $user->can(TenantPermissions::EDIT_INVENTORY);
    }
}
