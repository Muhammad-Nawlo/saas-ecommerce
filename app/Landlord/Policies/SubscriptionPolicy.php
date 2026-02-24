<?php

declare(strict_types=1);

namespace App\Landlord\Policies;

use App\Constants\LandlordPermissions;
use App\Landlord\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LandlordPermissions::VIEW_SUBSCRIPTIONS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->can(LandlordPermissions::VIEW_SUBSCRIPTIONS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->can(LandlordPermissions::MANAGE_SUBSCRIPTIONS)
            || $user->hasRole('super_admin')
            || $user->hasRole('finance_admin');
    }
}
