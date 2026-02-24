<?php

declare(strict_types=1);

namespace App\Landlord\Policies;

use App\Constants\LandlordPermissions;
use App\Landlord\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LandlordPermissions::VIEW_TENANTS)
            || $user->hasRole('super_admin')
            || $user->hasRole('support_agent')
            || $user->hasRole('finance_admin');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can(LandlordPermissions::VIEW_TENANTS)
            || $user->hasRole('super_admin')
            || $user->hasRole('support_agent')
            || $user->hasRole('finance_admin');
    }

    /** Suspend/activate tenant. */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can(LandlordPermissions::MANAGE_TENANTS)
            || $user->hasRole('super_admin');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('super_admin');
    }
}
