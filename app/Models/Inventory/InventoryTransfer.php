<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $product_id
 * @property string $from_location_id
 * @property string $to_location_id
 * @property int $quantity
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class InventoryTransfer extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'inventory_transfers';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'product_id',
        'from_location_id',
        'to_location_id',
        'quantity',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_id', 'id');
    }
}
