<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer\Customer;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Support\Facades\DB;

/**
 * When a guest creates an account, optionally link past orders (by email) to the new customer.
 */
final class LinkGuestOrdersToCustomerService
{
    public function linkByEmail(Customer $customer): int
    {
        $email = strtolower(trim($customer->email));
        $tenantId = $customer->tenant_id;

        return (int) DB::transaction(function () use ($customer, $email, $tenantId): int {
            $updated = OrderModel::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('user_id')
                ->where('customer_email', $email)
                ->update(['user_id' => $customer->id]);
            return $updated;
        });
    }
}
