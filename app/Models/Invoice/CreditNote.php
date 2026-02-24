<?php

declare(strict_types=1);

namespace App\Models\Invoice;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $reason
 * @property int $amount_cents
 * @property string $currency
 * @property \Illuminate\Support\Carbon $issued_at
 * @property array|null $snapshot
 */
class CreditNote extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'credit_notes';

    protected $fillable = [
        'invoice_id',
        'reason',
        'amount_cents',
        'currency',
        'issued_at',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'issued_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
