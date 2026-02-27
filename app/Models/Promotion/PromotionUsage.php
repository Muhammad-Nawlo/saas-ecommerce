<?php

declare(strict_types=1);

namespace App\Models\Promotion;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks promotion usage for total and per-customer limits.
 */
class PromotionUsage extends Model
{
    use HasUuids;

    protected $table = 'promotion_usages';

    public $timestamps = false;

    protected $fillable = ['promotion_id', 'user_id', 'customer_email', 'order_id', 'used_at'];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
