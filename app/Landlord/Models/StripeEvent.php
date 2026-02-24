<?php

declare(strict_types=1);

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $event_id
 * @property \Illuminate\Support\Carbon $processed_at
 */
class StripeEvent extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
    protected $connection;

    protected $table = 'stripe_events';

    protected $fillable = ['event_id', 'processed_at'];

    protected $casts = ['processed_at' => 'datetime'];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
        parent::__construct($attributes);
    }
}
