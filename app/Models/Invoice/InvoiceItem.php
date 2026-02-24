<?php

declare(strict_types=1);

namespace App\Models\Invoice;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property array|null $metadata
 */
class InvoiceItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_cents',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
