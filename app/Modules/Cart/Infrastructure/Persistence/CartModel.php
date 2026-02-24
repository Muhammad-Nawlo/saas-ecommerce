<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CartModel extends Model
{
    protected $table = 'carts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'customer_email',
        'session_id',
        'status',
        'total_amount',
        'currency',
    ];

    protected $casts = [
        'total_amount' => 'integer',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItemModel::class, 'cart_id', 'id');
    }
}
