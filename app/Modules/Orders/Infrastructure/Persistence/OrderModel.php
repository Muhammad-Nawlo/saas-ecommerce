<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrderModel extends Model
{
    use SoftDeletes;
    protected $table = 'orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'customer_id',
        'customer_email',
        'status',
        'total_amount',
        'currency',
        'discount_total_cents',
        'applied_promotions',
        'internal_notes',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'discount_total_cents' => 'integer',
        'applied_promotions' => 'array',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer\Customer::class, 'customer_id', 'id');
    }
}
