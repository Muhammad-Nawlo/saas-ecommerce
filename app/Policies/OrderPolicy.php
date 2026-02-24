<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null;
    }

    public function view(User $user, OrderModel $order): bool
    {
        return tenant('id') !== null && $order->tenant_id === tenant('id');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OrderModel $order): bool
    {
        return tenant('id') !== null && $order->tenant_id === tenant('id');
    }

    public function delete(User $user, OrderModel $order): bool
    {
        return false;
    }
}
