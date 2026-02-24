<?php

declare(strict_types=1);

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $code
 * @property float $price
 * @property string $billing_interval
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Plan extends Model
{
    use HasUuids;

    protected $connection;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'code',
        'price',
        'billing_interval',
        'stripe_price_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
        parent::__construct($attributes);
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('value')
            ->withTimestamps();
    }
}
