<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemModel extends Model
{
    protected $table = 'order_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'quantity',
        'unit_price_amount',
        'unit_price_currency',
        'total_price_amount',
        'total_price_currency',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_amount' => 'integer',
        'total_price_amount' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id', 'id');
    }
}
