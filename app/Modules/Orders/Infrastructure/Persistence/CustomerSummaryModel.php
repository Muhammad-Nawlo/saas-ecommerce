<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only model for customer_summaries view (tenant DB).
 * Represents distinct customers from orders with order_count and total_spent.
 */
final class CustomerSummaryModel extends Model
{
    protected $table = 'customer_summaries';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'email';

    protected $fillable = [];

    protected $casts = [
        'order_count' => 'integer',
        'total_spent' => 'integer',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
