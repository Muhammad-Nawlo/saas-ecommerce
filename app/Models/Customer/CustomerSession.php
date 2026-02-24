<?php

declare(strict_types=1);

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional security: track customer sessions (IP, user agent, last activity).
 *
 * @property string $id
 * @property string $customer_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 */
class CustomerSession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'customer_sessions';

    protected $fillable = [
        'customer_id',
        'ip_address',
        'user_agent',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
