<?php

declare(strict_types=1);

namespace App\Models\Invoice;

use App\Models\Customer\Customer;
use App\Models\Financial\FinancialOrder;
use App\Modules\Shared\Domain\Exceptions\InvoiceLockedException;
use App\Modules\Shared\Infrastructure\Audit\SnapshotHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
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
 * @property string|null $snapshot_hash
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
        'base_currency',
        'exchange_rate_snapshot',
        'total_base_cents',
        'subtotal_cents',
        'tax_total_cents',
        'discount_total_cents',
        'total_cents',
        'due_date',
        'issued_at',
        'paid_at',
        'snapshot',
        'snapshot_hash',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'tax_total_cents' => 'integer',
            'discount_total_cents' => 'integer',
            'total_cents' => 'integer',
            'exchange_rate_snapshot' => 'array',
            'total_base_cents' => 'integer',
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

    private const LOCKED_ATTRIBUTES = ['subtotal_cents', 'tax_total_cents', 'discount_total_cents', 'total_cents', 'currency', 'snapshot'];

    /**
     * Guard: block modification of totals and snapshot once invoice is issued. Throws InvoiceLockedException.
     */
    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $origStatus = $invoice->getOriginal('status');
            $alreadyIssued = in_array($origStatus, [self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_PARTIALLY_PAID, self::STATUS_REFUNDED], true);
            if (!$alreadyIssued && $invoice->getOriginal('locked_at') === null) {
                return;
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                if ($invoice->isDirty($attr)) {
                    Log::channel('security')->warning('Immutability violation: invoice update blocked', [
                        'entity_type' => 'invoice',
                        'entity_id' => $invoice->id,
                        'tenant_id' => $invoice->tenant_id,
                        'attribute' => $attr,
                        'invoice_number' => $invoice->invoice_number,
                    ]);
                    throw new InvoiceLockedException('Cannot modify issued invoice: ' . $attr . ' is immutable.');
                }
            }
            foreach (self::LOCKED_ATTRIBUTES as $attr) {
                $invoice->setAttribute($attr, $invoice->getOriginal($attr));
            }
        });
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

    /**
     * Tamper detection: recompute hash from current snapshot/immutable fields and compare to stored snapshot_hash.
     * Logs to security channel on mismatch. Does not auto-correct.
     */
    public function verifySnapshotIntegrity(): bool
    {
        if (! $this->isIssued() || $this->snapshot_hash === null) {
            return true;
        }
        $computed = SnapshotHash::hash($this->buildHashPayload());
        if ($computed !== $this->snapshot_hash) {
            Log::channel('security')->critical('Invoice snapshot hash mismatch', [
                'tenant_id' => $this->tenant_id,
                'invoice_id' => $this->id,
                'invoice_number' => $this->invoice_number,
                'stored_hash' => $this->snapshot_hash,
                'computed_hash' => $computed,
            ]);
            return false;
        }
        return true;
    }

    /** @return array<string, mixed> Fields included in snapshot hash (immutable after issue). */
    private function buildHashPayload(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_total_cents' => $this->tax_total_cents,
            'discount_total_cents' => $this->discount_total_cents,
            'total_cents' => $this->total_cents,
            'snapshot' => $this->snapshot,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
        ];
    }

    /** Set snapshot_hash from current immutable fields. Call at issue time only. */
    public function setSnapshotHashFromCurrentState(): void
    {
        $this->snapshot_hash = SnapshotHash::hash($this->buildHashPayload());
    }
}
