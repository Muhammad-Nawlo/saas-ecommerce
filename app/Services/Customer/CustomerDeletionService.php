<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer\Customer;
use Illuminate\Support\Str;

/**
 * GDPR: soft delete then anonymize personal data.
 */
final class CustomerDeletionService
{
    public function deleteAndAnonymize(Customer $customer): void
    {
        $customer->addresses()->delete();
        $customer->sessions()->delete();

        $anon = 'anon_' . Str::random(8) . '_' . now()->timestamp;
        $customer->email = $anon . '@deleted.local';
        $customer->first_name = 'Deleted';
        $customer->last_name = 'User';
        $customer->phone = null;
        $customer->meta = null;
        $customer->password = bcrypt(Str::random(32));
        $customer->is_active = false;
        $customer->save();

        $customer->delete();
    }
}
