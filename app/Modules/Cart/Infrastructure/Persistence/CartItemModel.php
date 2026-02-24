<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CartItemModel extends Model
{
    protected $table = 'cart_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'cart_id',
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

    public function cart(): BelongsTo
    {
        return $this->belongsTo(CartModel::class, 'cart_id', 'id');
    }
}
