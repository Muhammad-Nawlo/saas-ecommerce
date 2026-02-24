<?php

declare(strict_types=1);

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of tax applied at order lock. Do not use live tax_rates for locked orders.
 *
 * @property string $id
 * @property string $order_id
 * @property string $tax_rate_name
 * @property float $tax_percentage
 * @property int $taxable_amount_cents
 * @property int $tax_amount_cents
 */
class FinancialOrderTaxLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'financial_order_tax_lines';

    protected $fillable = [
        'order_id',
        'tax_rate_name',
        'tax_percentage',
        'taxable_amount_cents',
        'tax_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'tax_percentage' => 'decimal:2',
            'taxable_amount_cents' => 'integer',
            'tax_amount_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FinancialOrder::class, 'order_id');
    }
}
