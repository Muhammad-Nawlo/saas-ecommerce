<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $product_id
 * @property string $location_id
 * @property string $order_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class InventoryReservation extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'inventory_reservations';

    protected $fillable = [
        'product_id',
        'location_id',
        'order_id',
        'quantity',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }
}
