<?php

declare(strict_types=1);

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Feature extends Model
{
    public const string TYPE_LIMIT = 'limit';
    public const string TYPE_BOOLEAN = 'boolean';

    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $connection;

    protected $table = 'features';

    protected $fillable = [
        'code',
        'description',
        'type',
    ];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
        parent::__construct($attributes);
    }

    protected static function newFactory(): \Database\Factories\Landlord\FeatureFactory
    {
        return \Database\Factories\Landlord\FeatureFactory::new();
    }

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function isLimit(): bool
    {
        return $this->type === self::TYPE_LIMIT;
    }

    public function isBoolean(): bool
    {
        return $this->type === self::TYPE_BOOLEAN;
    }
}
