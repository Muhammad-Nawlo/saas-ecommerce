<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer\Customer;
use App\Models\User;

class CustomerIdentityPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null;
    }

    public function view(User $user, Customer $customer): bool
    {
        return tenant('id') !== null && $customer->tenant_id === tenant('id');
    }

    public function create(User $user): bool
    {
        return tenant('id') !== null;
    }

    public function update(User $user, Customer $customer): bool
    {
        return tenant('id') !== null && $customer->tenant_id === tenant('id');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return tenant('id') !== null && $customer->tenant_id === tenant('id');
    }
}
