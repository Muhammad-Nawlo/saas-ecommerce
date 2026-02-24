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
        'payment_currency',
        'payment_amount',
        'exchange_rate_snapshot',
        'payment_amount_base',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payment_amount' => 'integer',
        'payment_amount_base' => 'integer',
        'exchange_rate_snapshot' => 'array',
    ];

    /** Status value when payment is confirmed (succeeded). */
    public const STATUS_SUCCEEDED = 'succeeded';

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Guard: block updates to snapshot fields once payment is confirmed.
     */
    protected static function booted(): void
    {
        static::updating(function (PaymentModel $model): void {
            if ($model->getOriginal('status') === self::STATUS_SUCCEEDED) {
                $model->payment_currency = $model->getOriginal('payment_currency');
                $model->payment_amount = $model->getOriginal('payment_amount');
                $model->exchange_rate_snapshot = $model->getOriginal('exchange_rate_snapshot');
                $model->payment_amount_base = $model->getOriginal('payment_amount_base');
            }
        });
    }
}
