<?php

declare(strict_types=1);

namespace App\Policies;

use App\Constants\TenantPermissions;
use App\Models\User;
use App\Modules\Orders\Infrastructure\Persistence\CustomerSummaryModel;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::VIEW_CUSTOMERS);
    }

    public function view(User $user, CustomerSummaryModel $customer): bool
    {
        return tenant('id') !== null
            && $customer->tenant_id === tenant('id')
            && $user->can(TenantPermissions::VIEW_CUSTOMERS);
    }
}
