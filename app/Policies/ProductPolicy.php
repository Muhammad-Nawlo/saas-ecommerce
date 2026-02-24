<?php

declare(strict_types=1);

namespace App\Policies;

use App\Constants\TenantPermissions;
use App\Models\User;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::VIEW_PRODUCTS);
    }

    public function view(User $user, ProductModel $product): bool
    {
        return tenant('id') !== null
            && $product->tenant_id === tenant('id')
            && $user->can(TenantPermissions::VIEW_PRODUCTS);
    }

    public function create(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::CREATE_PRODUCTS);
    }

    public function update(User $user, ProductModel $product): bool
    {
        return tenant('id') !== null
            && $product->tenant_id === tenant('id')
            && $user->can(TenantPermissions::EDIT_PRODUCTS);
    }

    public function delete(User $user, ProductModel $product): bool
    {
        return tenant('id') !== null
            && $product->tenant_id === tenant('id')
            && $user->can(TenantPermissions::DELETE_PRODUCTS);
    }
}
