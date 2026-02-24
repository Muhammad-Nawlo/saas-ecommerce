<?php

declare(strict_types=1);

namespace App\Modules\Financial\Application\Services;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialTransaction;
use App\Models\Invoice\Invoice;
use App\Models\Ledger\LedgerEntry;
use App\Models\Ledger\LedgerTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Detects financial inconsistencies across financial_orders, invoices, payments, ledger.
 * Does NOT auto-fix; only logs structured errors for investigation.
 */
final class FinancialReconciliationService
{
    private const MISMATCH_LEDGER_UNBALANCED = 'ledger_unbalanced';
    private const MISMATCH_INVOICE_TOTAL = 'invoice_total_mismatch';
    private const MISMATCH_PAYMENTS_SUM = 'payments_sum_mismatch';

    /**
     * Run reconciliation for the current tenant context.
     * Call from a job or command that has already initialized tenancy.
     *
     * @return array<string, mixed> List of detected issues (for testing/reporting)
     */
    public function reconcile(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? (string) (tenant()?->getTenantKey() ?? '');
        $issues = [];

        $orders = FinancialOrder::when($tenantId !== '', fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        foreach ($orders as $order) {
            $this->checkLedgerBalanced($order, $issues);
            $this->checkInvoiceTotal($order, $issues);
            if ($order->status === FinancialOrder::STATUS_PAID) {
                $this->checkPaymentsSum($order, $issues);
            }
        }

        return $issues;
    }

    /**
     * For each ledger transaction referencing this order, ensure debits == credits.
     */
    private function checkLedgerBalanced(FinancialOrder $order, array &$issues): void
    {
        $transactions = LedgerTransaction::whereIn('reference_type', ['financial_order', 'refund'])
            ->where('reference_id', $order->id)
            ->with('entries')
            ->get();

        foreach ($transactions as $tx) {
            $debits = 0;
            $credits = 0;
            foreach ($tx->entries as $entry) {
                $cents = (int) $entry->amount_cents;
                if ($entry->type === LedgerEntry::TYPE_DEBIT) {
                    $debits += $cents;
                } elseif ($entry->type === LedgerEntry::TYPE_CREDIT) {
                    $credits += $cents;
                }
            }
            if ($debits !== $credits) {
                $payload = [
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->operational_order_id,
                    'financial_order_id' => $order->id,
                    'ledger_transaction_id' => $tx->id,
                    'mismatch_type' => self::MISMATCH_LEDGER_UNBALANCED,
                    'debits_cents' => $debits,
                    'credits_cents' => $credits,
                ];
                $issues[] = $payload;
                Log::channel('stack')->warning('Financial reconciliation: ledger unbalanced', $payload);
            }
        }
    }

    /**
     * Compare invoice total vs financial_order total (for orders that have an invoice).
     */
    private function checkInvoiceTotal(FinancialOrder $order, array &$issues): void
    {
        $invoices = Invoice::where('order_id', $order->id)->get();
        if ($invoices->isEmpty()) {
            return;
        }
        $orderTotal = (int) $order->total_cents;
        foreach ($invoices as $invoice) {
            $invoiceTotal = (int) $invoice->total_cents;
            if ($invoiceTotal !== $orderTotal) {
                $payload = [
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->operational_order_id,
                    'financial_order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'mismatch_type' => self::MISMATCH_INVOICE_TOTAL,
                    'financial_order_total_cents' => $orderTotal,
                    'invoice_total_cents' => $invoiceTotal,
                ];
                $issues[] = $payload;
                Log::channel('stack')->warning('Financial reconciliation: invoice total mismatch', $payload);
            }
        }
    }

    /**
     * For paid orders: sum of completed CREDIT financial_transactions must equal order total.
     */
    private function checkPaymentsSum(FinancialOrder $order, array &$issues): void
    {
        $paymentsSum = (int) FinancialTransaction::where('order_id', $order->id)
            ->where('type', FinancialTransaction::TYPE_CREDIT)
            ->where('status', FinancialTransaction::STATUS_COMPLETED)
            ->sum('amount_cents');
        $orderTotal = (int) $order->total_cents;
        if ($paymentsSum !== $orderTotal) {
            $payload = [
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->operational_order_id,
                'financial_order_id' => $order->id,
                'mismatch_type' => self::MISMATCH_PAYMENTS_SUM,
                'financial_order_total_cents' => $orderTotal,
                'payments_sum_cents' => $paymentsSum,
            ];
            $issues[] = $payload;
            Log::channel('stack')->warning('Financial reconciliation: payments sum mismatch', $payload);
        }
    }
}
