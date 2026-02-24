<?php

declare(strict_types=1);

namespace App\Models\Financial;

use App\Modules\Shared\Domain\Exceptions\FinancialOrderLockedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Financial order. Immutable when locked_at is set.
 *
 * @property string $id
 * @property string|null $operational_order_id
 * @property string|null $tenant_id
 * @property string|null $property_id
 * @property string $order_number
 * @property int $subtotal_cents
 * @property int $tax_total_cents
 * @property int $discount_total_cents
 * @property int $total_cents
 * @property string $currency
 * @property string|null $base_currency
 * @property string|null $display_currency
 * @property array|null $exchange_rate_snapshot
 * @property int|null $subtotal_base_cents
 * @property int|null $subtotal_display_cents
 * @property int|null $tax_base_cents
 * @property int|null $tax_display_cents
 * @property int|null $total_base_cents
 * @property int|null $total_display_cents
 * @property string $status
 * @property array|null $snapshot
 * @property \Illuminate\Support\Carbon|null $locked_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class FinancialOrder extends Model
{
    use HasUuids;

    protected $table = 'financial_orders';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'operational_order_id',
        'tenant_id',
        'property_id',
        'order_number',
        'subtotal_cents',
        'tax_total_cents',
        'discount_total_cents',
        'total_cents',
        'currency',
        'base_currency',
        'display_currency',
        'exchange_rate_snapshot',
        'subtotal_base_cents',
        'subtotal_display_cents',
        'tax_base_cents',
        'tax_display_cents',
        'total_base_cents',
        'total_display_cents',
        'status',
        'snapshot',
        'locked_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'tax_total_cents' => 'integer',
            'discount_total_cents' => 'integer',
            'total_cents' => 'integer',
            'exchange_rate_snapshot' => 'array',
            'subtotal_base_cents' => 'integer',
            'subtotal_display_cents' => 'integer',
            'tax_base_cents' => 'integer',
            'tax_display_cents' => 'integer',
            'total_base_cents' => 'integer',
            'total_display_cents' => 'integer',
            'snapshot' => 'array',
            'locked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinancialOrderItem::class, 'order_id');
    }

    public function taxLines(): HasMany
    {
        return $this->hasMany(FinancialOrderTaxLine::class, 'order_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'order_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    private const LOCKED_ATTRIBUTES = [
        'subtotal_cents', 'tax_total_cents', 'discount_total_cents', 'total_cents', 'currency',
        'base_currency', 'display_currency', 'exchange_rate_snapshot',
        'subtotal_base_cents', 'subtotal_display_cents', 'tax_base_cents', 'tax_display_cents',
        'total_base_cents', 'total_display_cents', 'snapshot',
    ];

    /**
     * Guard: block modification of financial fields when order is no longer draft.
     * Throws FinancialOrderLockedException on mutation attempt; then reverts to prevent persistence.
     */
    protected static function booted(): void
    {
        static::updating(function (FinancialOrder $order): void {
            if ($order->getOriginal('status') === self::STATUS_DRAFT) {
                return;
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                if ($order->isDirty($attr)) {
                    throw new FinancialOrderLockedException(
                        'Cannot modify financial order after lock: ' . $attr . ' is immutable.'
                    );
                }
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                $order->setAttribute($attr, $order->getOriginal($attr));
            }
        });
    }

    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }
        return $query->where('tenant_id', $tenantId);
    }
}
