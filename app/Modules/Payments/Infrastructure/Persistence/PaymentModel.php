<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Persistence;

use App\Modules\Shared\Domain\Exceptions\PaymentConfirmedException;
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

    /** Attributes immutable once payment is confirmed/succeeded. */
    private const LOCKED_ATTRIBUTES = ['amount', 'currency', 'payment_currency', 'payment_amount', 'exchange_rate_snapshot', 'payment_amount_base'];

    /**
     * Guard: block updates to amount, currency and snapshot once payment is confirmed. Throws PaymentConfirmedException.
     */
    protected static function booted(): void
    {
        static::updating(function (PaymentModel $model): void {
            if ($model->getOriginal('status') !== self::STATUS_SUCCEEDED) {
                return;
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                if ($model->isDirty($attr)) {
                    throw new PaymentConfirmedException('Cannot modify confirmed payment: ' . $attr . ' is immutable.');
                }
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                $model->setAttribute($attr, $model->getOriginal($attr));
            }
        });
    }
}
