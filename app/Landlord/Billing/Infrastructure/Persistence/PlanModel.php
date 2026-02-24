<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $stripe_price_id
 * @property int $price_amount
 * @property string $currency
 * @property string $billing_interval
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PlanModel extends Model
{
    use HasUuids;

    protected $connection;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'stripe_price_id',
        'price_amount',
        'currency',
        'billing_interval',
        'is_active',
    ];

    protected $casts = [
        'price_amount' => 'integer',
        'is_active' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
        parent::__construct($attributes);
    }
}
