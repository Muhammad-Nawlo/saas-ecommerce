<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CategoryModel extends Model
{
    protected $table = 'categories';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'slug',
        'parent_id',
        'status',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'parent_id', 'id');
    }

    /**
     * @return HasMany<CategoryModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(CategoryModel::class, 'parent_id', 'id');
    }
}
