<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\CreditNote;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\InvoicePayment;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Modules\Shared\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Invoice lifecycle: create from order (draft) → issue (locked) → apply payments / credit notes.
 * All totals from snapshot; no recalculation after issuance.
 */
final class InvoiceService
{
    public function __construct(
        private InvoiceNumberGenerator $numberGenerator,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Create draft invoice from order snapshot. Order must be locked (have snapshot) and paid or pending.
     */
    public function createFromOrder(FinancialOrder $order): Invoice
    {
        if (!$order->isLocked() || $order->snapshot === null) {
            throw new InvalidArgumentException('Order must be locked with snapshot to create invoice.');
        }
        $allowedStatuses = [FinancialOrder::STATUS_PAID, FinancialOrder::STATUS_PENDING];
        if (!in_array($order->status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Order status must be paid or pending to create invoice.');
        }

        $tenantId = $order->tenant_id ?? (string) tenant('id');
        if ($tenantId === '') {
            throw new InvalidArgumentException('Tenant context required.');
        }

        $snapshot = $order->snapshot;
        $subtotal_cents = (int) ($snapshot['subtotal_cents'] ?? 0);
        $tax_total_cents = (int) ($snapshot['tax_total_cents'] ?? 0);
        $total_cents = (int) ($snapshot['total_cents'] ?? 0);
        $discount_total_cents = 0;
        $currency = (string) ($snapshot['currency'] ?? $order->currency);

        return DB::transaction(function () use (
            $order,
            $tenantId,
            $snapshot,
            $subtotal_cents,
            $tax_total_cents,
            $discount_total_cents,
            $total_cents,
            $currency,
        ): Invoice {
            $invoiceNumber = $this->numberGenerator->generate($tenantId);
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'customer_id' => null,
                'invoice_number' => $invoiceNumber,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $currency,
                'subtotal_cents' => $subtotal_cents,
                'tax_total_cents' => $tax_total_cents,
                'discount_total_cents' => $discount_total_cents,
                'total_cents' => $total_cents,
                'due_date' => null,
                'snapshot' => $snapshot,
            ]);

            $items = $snapshot['items'] ?? [];
            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => (string) ($item['description'] ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price_cents' => (int) ($item['unit_price_cents'] ?? 0),
                    'subtotal_cents' => (int) ($item['subtotal_cents'] ?? 0),
                    'tax_cents' => (int) ($item['tax_cents'] ?? 0),
                    'total_cents' => (int) ($item['total_cents'] ?? 0),
                    'metadata' => $item['metadata'] ?? null,
                ]);
            }

            $this->auditLogger->logStructuredTenantAction(
                'invoice_created',
                'Invoice created from order: ' . $invoice->invoice_number,
                $invoice,
                null,
                ['status' => Invoice::STATUS_DRAFT],
                ['order_id' => $order->id, 'ip' => request()->ip()],
            );

            return $invoice;
        });
    }

    /**
     * Issue invoice: lock, set issued_at, status = issued. Immutable after this.
     */
    public function issue(Invoice $invoice): void
    {
        if ($invoice->isIssued()) {
            throw new InvalidArgumentException('Invoice already issued.');
        }
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft invoices can be issued.');
        }

        $oldStatus = $invoice->status;
        DB::transaction(function () use ($invoice): void {
            $invoice->status = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->locked_at = now();
            $invoice->snapshot = array_merge($invoice->snapshot ?? [], [
                'issued_at' => $invoice->issued_at->toIso8601String(),
                'subtotal_cents' => $invoice->subtotal_cents,
                'tax_total_cents' => $invoice->tax_total_cents,
                'discount_total_cents' => $invoice->discount_total_cents,
                'total_cents' => $invoice->total_cents,
            ]);
            $invoice->setSnapshotHashFromCurrentState();
            $invoice->save();
        });

        $this->auditLogger->logStructuredTenantAction(
            'invoice_issued',
            'Invoice issued: ' . $invoice->invoice_number,
            $invoice,
            ['status' => $oldStatus],
            ['status' => Invoice::STATUS_ISSUED],
            ['ip' => request()->ip()],
        );
        Log::info('Invoice issued', [
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'order_id' => $invoice->order_id,
        ]);
    }

    /**
     * Apply payment. Cannot exceed remaining balance. Uses Money VO.
     */
    public function applyPayment(Invoice $invoice, Money $amount, ?string $financialTransactionId = null): void
    {
        if (!$invoice->isIssued()) {
            throw new InvalidArgumentException('Only issued invoices can receive payments.');
        }
        if ($invoice->status === Invoice::STATUS_VOID) {
            throw new InvalidArgumentException('Cannot apply payment to void invoice.');
        }
        if (strtoupper($amount->getCurrency()) !== strtoupper($invoice->currency)) {
            throw new InvalidArgumentException('Payment currency must match invoice currency.');
        }
        $balanceCents = $invoice->balanceDueCents();
        if ($amount->getMinorUnits() > $balanceCents) {
            throw new InvalidArgumentException('Payment cannot exceed remaining balance.');
        }
        if ($amount->getMinorUnits() <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }

        DB::transaction(function () use ($invoice, $amount, $financialTransactionId): void {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'financial_transaction_id' => $financialTransactionId,
                'amount_cents' => $amount->getMinorUnits(),
                'currency' => $amount->getCurrency(),
                'paid_at' => now(),
            ]);
            $invoice->refresh();
            $paid = $invoice->totalPaidCents();
            $total = $invoice->total_cents;
            $credits = $invoice->totalCreditNotesCents();
            $balance = $total - $paid - $credits;
            if ($balance <= 0) {
                $invoice->status = Invoice::STATUS_PAID;
                $invoice->paid_at = now();
            } else {
                $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
            }
            $invoice->save();
        });

        $this->auditLogger->logTenantAction(
            'invoice_paid',
            'Payment applied to invoice: ' . $invoice->invoice_number,
            $invoice,
            [
                'amount_cents' => $amount->getMinorUnits(),
                'actor_id' => auth()->id(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Create credit note. Cannot exceed invoice total. Snapshots invoice totals.
     */
    public function createCreditNote(Invoice $invoice, Money $amount, string $reason): CreditNote
    {
        if (!$invoice->isIssued()) {
            throw new InvalidArgumentException('Only issued invoices can have credit notes.');
        }
        if ($invoice->status === Invoice::STATUS_VOID) {
            throw new InvalidArgumentException('Cannot create credit note for void invoice.');
        }
        if (strtoupper($amount->getCurrency()) !== strtoupper($invoice->currency)) {
            throw new InvalidArgumentException('Credit note currency must match invoice.');
        }
        if ($amount->getMinorUnits() <= 0) {
            throw new InvalidArgumentException('Credit note amount must be positive.');
        }
        $maxCredit = $invoice->total_cents - $invoice->totalCreditNotesCents();
        if ($amount->getMinorUnits() > $maxCredit) {
            throw new InvalidArgumentException('Credit note cannot exceed invoice total minus existing credits.');
        }

        return DB::transaction(function () use ($invoice, $amount, $reason): CreditNote {
            $snapshot = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_cents' => $invoice->total_cents,
                'issued_at' => now()->toIso8601String(),
            ];
            $creditNote = CreditNote::create([
                'invoice_id' => $invoice->id,
                'reason' => $reason,
                'amount_cents' => $amount->getMinorUnits(),
                'currency' => $invoice->currency,
                'issued_at' => now(),
                'snapshot' => $snapshot,
            ]);
            $invoice->refresh();
            $credits = $invoice->totalCreditNotesCents();
            $paid = $invoice->totalPaidCents();
            $balance = $invoice->total_cents - $paid - $credits;
            if ($balance <= 0 && $invoice->status !== Invoice::STATUS_REFUNDED) {
                $invoice->status = Invoice::STATUS_REFUNDED;
                $invoice->save();
            }

            $this->auditLogger->logTenantAction(
                'credit_note_created',
                'Credit note created for invoice: ' . $invoice->invoice_number,
                $creditNote,
                [
                    'amount_cents' => $amount->getMinorUnits(),
                    'reason' => $reason,
                    'actor_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'timestamp' => now()->toIso8601String(),
                ],
            );

            return $creditNote;
        });
    }

    /**
     * Void invoice. Only draft or issued can be voided.
     */
    public function void(Invoice $invoice): void
    {
        if (!in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID], true)) {
            throw new InvalidArgumentException('Invoice cannot be voided in current status.');
        }
        $oldStatus = $invoice->status;
        $invoice->status = Invoice::STATUS_VOID;
        $invoice->locked_at = $invoice->locked_at ?? now();
        $invoice->save();

        $this->auditLogger->logTenantAction(
            'invoice_voided',
            'Invoice voided: ' . $invoice->invoice_number,
            $invoice,
            [
                'old_status' => $oldStatus,
                'new_status' => Invoice::STATUS_VOID,
                'actor_id' => auth()->id(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }
}
