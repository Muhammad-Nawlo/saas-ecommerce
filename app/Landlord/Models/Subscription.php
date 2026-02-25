<?php

declare(strict_types=1);

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Subscription extends Model
{
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_CANCELED = 'canceled';
    public const string STATUS_PAST_DUE = 'past_due';

    use HasFactory;
    use HasUuids;

    protected $connection;

    protected $table = 'subscriptions';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'stripe_subscription_id',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
        parent::__construct($attributes);
    }

    protected static function newFactory(): \Database\Factories\Landlord\SubscriptionFactory
    {
        return \Database\Factories\Landlord\SubscriptionFactory::new();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
