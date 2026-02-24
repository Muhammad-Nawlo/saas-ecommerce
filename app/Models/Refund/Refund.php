<?php

declare(strict_types=1);

namespace App\Models\Refund;

use App\Models\Financial\FinancialOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refund record. Tracks amount, reason, status; links to financial order and optional financial_transaction.
 */
class Refund extends Model
{
    use HasUuids;

    protected $table = 'refunds';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'financial_order_id',
        'amount_cents',
        'currency',
        'reason',
        'status',
        'payment_reference',
        'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function financialOrder(): BelongsTo
    {
        return $this->belongsTo(FinancialOrder::class, 'financial_order_id');
    }
}
