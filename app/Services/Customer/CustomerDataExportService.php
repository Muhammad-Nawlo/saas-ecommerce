<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer\Customer;

/**
 * GDPR: export all data we hold for a customer.
 */
final class CustomerDataExportService
{
    public function export(Customer $customer): array
    {
        $customer->load('addresses');
        return [
            'exported_at' => now()->toIso8601String(),
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
                'created_at' => $customer->created_at->toIso8601String(),
                'updated_at' => $customer->updated_at->toIso8601String(),
            ],
            'addresses' => $customer->addresses->map(fn ($a) => [
                'type' => $a->type,
                'line1' => $a->line1,
                'line2' => $a->line2,
                'city' => $a->city,
                'state' => $a->state,
                'postal_code' => $a->postal_code,
                'country_code' => $a->country_code,
            ])->all(),
        ];
    }
}
