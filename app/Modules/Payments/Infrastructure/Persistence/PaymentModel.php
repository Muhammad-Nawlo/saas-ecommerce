<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PaymentModel extends Model
{
    protected $table = 'payments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_payment_id',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
