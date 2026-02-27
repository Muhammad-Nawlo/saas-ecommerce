<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderPaid;
use App\Models\Ledger\LedgerAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Log;

/**
 * CreateLedgerTransactionOnOrderPaidListener
 *
 * Creates a balanced double-entry ledger transaction (CASH debit, REV/TAX credit) when OrderPaid is fired.
 * Idempotent: skips if ledger transaction already exists for this financial_order. Uses LedgerService.
 * Critical for financial integrity; reconciliation checks debits == credits per transaction.
 *
 * Who dispatches OrderPaid: SyncFinancialOrderOnPaymentSucceededListener and payment confirmation flow.
 *
 * Assumes tenant context. Writes ledger_transactions, ledger_entries (tenant DB). Amounts in cents.
 */
final class CreateLedgerTransactionOnOrderPaidListener
{
    public function __construct(
        private LedgerService $ledgerService
    ) {
    }

    /**
     * Create balanced ledger transaction for order paid. Skips if tenant missing or accounts (CASH, REV, TAX) missing or existing tx.
     *
     * @param OrderPaid $event
     * @return void
     * Side effects: Writes LedgerTransaction, LedgerEntry; log. Requires tenant context.
     */
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $tenantId = $order->tenant_id;
        if ($tenantId === null || $tenantId === '') {
            return;
        }

        $ledger = $this->ledgerService->getOrCreateLedgerForTenant($tenantId, $order->currency);
        $existing = $ledger->transactions()
            ->where('reference_type', 'financial_order')
            ->where('reference_id', $order->id)
            ->exists();
        if ($existing) {
            return;
        }

        $cash = LedgerAccount::where('ledger_id', $ledger->id)->where('code', 'CASH')->first();
        $rev = LedgerAccount::where('ledger_id', $ledger->id)->where('code', 'REV')->first();
        $tax = LedgerAccount::where('ledger_id', $ledger->id)->where('code', 'TAX')->first();
        if ($cash === null || $rev === null || $tax === null) {
            Log::warning('CreateLedgerTransactionOnOrderPaid: missing ledger accounts', ['ledger_id' => $ledger->id]);
            return;
        }

        $discountCents = (int) ($order->discount_total_cents ?? 0);
        $revenueCents = $order->subtotal_cents - $discountCents;
        $taxCents = (int) $order->tax_total_cents;
        $totalCents = (int) $order->total_cents;

        $entries = [
            ['account_id' => $cash->id, 'type' => 'debit', 'amount_cents' => $totalCents, 'currency' => $order->currency, 'memo' => 'Payment received'],
            ['account_id' => $rev->id, 'type' => 'credit', 'amount_cents' => $revenueCents, 'currency' => $order->currency, 'memo' => 'Order revenue'],
            ['account_id' => $tax->id, 'type' => 'credit', 'amount_cents' => $taxCents, 'currency' => $order->currency, 'memo' => 'Tax collected'],
        ];

        $tx = $this->ledgerService->createTransaction(
            $ledger->id,
            'financial_order',
            $order->id,
            'Order paid: ' . $order->order_number,
            $entries
        );
        Log::channel('stack')->info('ledger_transaction_created', [
            'tenant_id' => $tenantId,
            'order_id' => $order->id,
            'financial_order_id' => $order->id,
            'ledger_transaction_id' => $tx->id,
        ]);
    }
}
