<?php

declare(strict_types=1);

namespace App\Landlord\Policies;

use App\Constants\LandlordPermissions;
use App\Landlord\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LandlordPermissions::VIEW_PLANS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->can(LandlordPermissions::VIEW_PLANS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }

    public function create(User $user): bool
    {
        return $user->can(LandlordPermissions::MANAGE_PLANS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can(LandlordPermissions::MANAGE_PLANS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->hasRole('super_admin');
    }
}
