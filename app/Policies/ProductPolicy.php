<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null;
    }

    public function view(User $user, ProductModel $product): bool
    {
        return tenant('id') !== null && $product->tenant_id === tenant('id');
    }

    public function create(User $user): bool
    {
        return tenant('id') !== null;
    }

    public function update(User $user, ProductModel $product): bool
    {
        return tenant('id') !== null && $product->tenant_id === tenant('id');
    }

    public function delete(User $user, ProductModel $product): bool
    {
        return tenant('id') !== null && $product->tenant_id === tenant('id');
    }
}
