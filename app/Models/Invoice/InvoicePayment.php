<?php

declare(strict_types=1);

namespace App\Models\Invoice;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string|null $financial_transaction_id
 * @property int $amount_cents
 * @property string $currency
 * @property \Illuminate\Support\Carbon $paid_at
 */
class InvoicePayment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'invoice_payments';

    protected $fillable = [
        'invoice_id',
        'financial_transaction_id',
        'amount_cents',
        'currency',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
