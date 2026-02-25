<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Persistence;

use App\Modules\Shared\Domain\Exceptions\PaymentConfirmedException;
use App\Modules\Shared\Infrastructure\Audit\SnapshotHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        'snapshot_hash',
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
     * On first save with status=succeeded, set snapshot_hash for tamper detection.
     */
    protected static function booted(): void
    {
        static::saving(function (PaymentModel $model): void {
            if ($model->status === self::STATUS_SUCCEEDED && $model->snapshot_hash === null) {
                $model->snapshot_hash = SnapshotHash::hash($model->buildHashPayload());
            }
        });
        static::updating(function (PaymentModel $model): void {
            if ($model->getOriginal('status') !== self::STATUS_SUCCEEDED) {
                return;
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                if ($model->isDirty($attr)) {
                    Log::channel('security')->warning('Immutability violation: confirmed payment update blocked', [
                        'entity_type' => 'payment',
                        'entity_id' => $model->id,
                        'tenant_id' => $model->tenant_id,
                        'attribute' => $attr,
                        'order_id' => $model->order_id,
                    ]);
                    throw new PaymentConfirmedException('Cannot modify confirmed payment: ' . $attr . ' is immutable.');
                }
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                $model->setAttribute($attr, $model->getOriginal($attr));
            }
        });
    }

    /**
     * Tamper detection: recompute hash and compare to stored snapshot_hash. Logs to security channel on mismatch.
     */
    public function verifySnapshotIntegrity(): bool
    {
        if ($this->status !== self::STATUS_SUCCEEDED || $this->snapshot_hash === null) {
            return true;
        }
        $computed = SnapshotHash::hash($this->buildHashPayload());
        if ($computed !== $this->snapshot_hash) {
            Log::channel('security')->critical('Payment snapshot hash mismatch', [
                'tenant_id' => $this->tenant_id,
                'payment_id' => $this->id,
                'order_id' => $this->order_id,
                'stored_hash' => $this->snapshot_hash,
                'computed_hash' => $computed,
            ]);
            return false;
        }
        return true;
    }

    /** @return array<string, mixed> Fields included in snapshot hash (immutable after confirm). */
    public function buildHashPayload(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_currency' => $this->payment_currency,
            'payment_amount' => $this->payment_amount,
            'exchange_rate_snapshot' => $this->exchange_rate_snapshot,
            'payment_amount_base' => $this->payment_amount_base,
        ];
    }
}
