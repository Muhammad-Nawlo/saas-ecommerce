<?php

declare(strict_types=1);

namespace App\Models\Promotion;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Promotion rule. Snapshot applied promotions in order; immutable after order lock.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $type percentage|fixed|free_shipping|threshold|bogo
 * @property int $value_cents
 * @property float|null $percentage
 * @property int $min_cart_cents
 * @property int|null $buy_quantity
 * @property int|null $get_quantity
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_stackable
 * @property int|null $max_uses_total
 * @property int|null $max_uses_per_customer
 * @property bool $is_active
 */
class Promotion extends Model
{
    use HasUuids;

    protected $table = 'promotions';

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FREE_SHIPPING = 'free_shipping';
    public const TYPE_THRESHOLD = 'threshold';
    public const TYPE_BOGO = 'bogo';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'value_cents',
        'percentage',
        'min_cart_cents',
        'buy_quantity',
        'get_quantity',
        'starts_at',
        'ends_at',
        'is_stackable',
        'max_uses_total',
        'max_uses_per_customer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value_cents' => 'integer',
            'percentage' => 'float',
            'min_cart_cents' => 'integer',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_stackable' => 'boolean',
            'max_uses_total' => 'integer',
            'max_uses_per_customer' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function couponCodes(): HasMany
    {
        return $this->hasMany(CouponCode::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }
}
