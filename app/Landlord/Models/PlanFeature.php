<?php

declare(strict_types=1);

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $feature_id
 * @property string $value
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PlanFeature extends Model
{
    use HasUuids;

    protected $connection;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
    ];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
        parent::__construct($attributes);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
