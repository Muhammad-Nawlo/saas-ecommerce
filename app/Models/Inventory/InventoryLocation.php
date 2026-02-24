<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property array|null $address
 * @property bool $is_active
 */
class InventoryLocation extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'inventory_locations';

    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_RETAIL_STORE = 'retail_store';
    public const TYPE_FULFILLMENT_CENTER = 'fulfillment_center';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryLocationStock::class, 'location_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
