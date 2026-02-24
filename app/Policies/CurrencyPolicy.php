<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Currency\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Currency $currency): bool
    {
        return true;
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->can('manage billing') || $user->hasRole('owner') || $user->hasRole('manager');
    }
}
