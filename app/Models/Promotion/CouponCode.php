<?php

declare(strict_types=1);

namespace App\Models\Promotion;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coupon code linked to a promotion. Usage limits enforced via promotion and promotion_usages.
 */
class CouponCode extends Model
{
    use HasUuids;

    protected $table = 'coupon_codes';

    protected $fillable = ['promotion_id', 'code', 'usage_count'];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
