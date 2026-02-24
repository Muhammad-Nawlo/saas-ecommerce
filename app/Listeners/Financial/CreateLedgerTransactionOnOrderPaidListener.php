<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderPaid;
use App\Models\Ledger\LedgerAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Log;

/**
 * Creates a balanced double-entry ledger transaction when an order is paid.
 * Idempotent: skips if a ledger transaction already exists for this financial order.
 */
final class CreateLedgerTransactionOnOrderPaidListener
{
    public function __construct(
        private LedgerService $ledgerService
    ) {
    }

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

        $this->ledgerService->createTransaction(
            $ledger->id,
            'financial_order',
            $order->id,
            'Order paid: ' . $order->order_number,
            $entries
        );
    }
}
