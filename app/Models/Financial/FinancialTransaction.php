<?php

declare(strict_types=1);

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $order_id
 * @property string $type
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $provider_reference
 * @property string $status
 * @property array|null $meta
 */
class FinancialTransaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'financial_transactions';

    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_REFUND = 'refund';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'type',
        'amount_cents',
        'currency',
        'provider_reference',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FinancialOrder::class, 'order_id');
    }
}
