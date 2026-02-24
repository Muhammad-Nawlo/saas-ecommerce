<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only log. Never update quantity without inserting a movement.
 *
 * @property string $id
 * @property string $product_id
 * @property string $location_id
 * @property string $type
 * @property int $quantity
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property array|null $meta
 */
class InventoryMovement extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'inventory_movements';

    public const TYPE_INCREASE = 'increase';
    public const TYPE_DECREASE = 'decrease';
    public const TYPE_RESERVE = 'reserve';
    public const TYPE_RELEASE = 'release';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'product_id',
        'location_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'meta' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }
}
