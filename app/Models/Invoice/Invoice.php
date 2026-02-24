<?php

declare(strict_types=1);

namespace App\Models\Invoice;

use App\Models\Customer\Customer;
use App\Models\Financial\FinancialOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Immutable after issued_at / locked_at. Totals from snapshot only.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $order_id
 * @property string|null $customer_id
 * @property string $invoice_number
 * @property string $status
 * @property string $currency
 * @property int $subtotal_cents
 * @property int $tax_total_cents
 * @property int $discount_total_cents
 * @property int $total_cents
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property array|null $snapshot
 * @property \Illuminate\Support\Carbon|null $locked_at
 */
class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'invoices';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_VOID = 'void';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'customer_id',
        'invoice_number',
        'status',
        'currency',
        'subtotal_cents',
        'tax_total_cents',
        'discount_total_cents',
        'total_cents',
        'due_date',
        'issued_at',
        'paid_at',
        'snapshot',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'tax_total_cents' => 'integer',
            'discount_total_cents' => 'integer',
            'total_cents' => 'integer',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'locked_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class, 'invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'invoice_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FinancialOrder::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_PARTIALLY_PAID, self::STATUS_REFUNDED], true);
    }

    public function totalPaidCents(): int
    {
        return (int) $this->payments()->sum('amount_cents');
    }

    public function totalCreditNotesCents(): int
    {
        return (int) $this->creditNotes()->sum('amount_cents');
    }

    public function balanceDueCents(): int
    {
        return $this->total_cents - $this->totalPaidCents() - $this->totalCreditNotesCents();
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
