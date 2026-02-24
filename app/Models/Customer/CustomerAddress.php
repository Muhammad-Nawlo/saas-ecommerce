<?php

declare(strict_types=1);

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $customer_id
 * @property string $type
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $state
 * @property string $postal_code
 * @property string $country_code
 * @property bool $is_default
 */
class CustomerAddress extends Model
{
    use HasUuids;

    protected $table = 'customer_addresses';

    public const TYPE_BILLING = 'billing';
    public const TYPE_SHIPPING = 'shipping';

    protected $fillable = [
        'customer_id',
        'type',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
