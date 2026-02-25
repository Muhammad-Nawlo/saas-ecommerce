<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderRefunded;
use App\Models\Ledger\LedgerAccount;
use App\Support\Instrumentation;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Creates a balanced reversing ledger transaction when an order is refunded.
 */
final class CreateLedgerReversalOnOrderRefundedListener
{
    public function __construct(
        private LedgerService $ledgerService
    ) {
    }

    private const IDEMPOTENCY_TTL_SECONDS = 86400;

    public function handle(OrderRefunded $event): void
    {
        $order = $event->order;
        $tenantId = $order->tenant_id;
        $refundCents = $event->amountCents;
        if ($tenantId === null || $tenantId === '' || $refundCents <= 0) {
            return;
        }

        $idempotencyKey = tenant_cache_key(
            sprintf('refund_ledger:%s:%d:%s', $order->id, $refundCents, $event->providerReference ?? ''),
            (string) $tenantId
        );
        if (Cache::has($idempotencyKey)) {
            return;
        }

        $ledger = $this->ledgerService->getOrCreateLedgerForTenant($tenantId, $order->currency);
        $cash = LedgerAccount::where('ledger_id', $ledger->id)->where('code', 'CASH')->first();
        $rev = LedgerAccount::where('ledger_id', $ledger->id)->where('code', 'REV')->first();
        $tax = LedgerAccount::where('ledger_id', $ledger->id)->where('code', 'TAX')->first();
        if ($cash === null || $rev === null || $tax === null) {
            Log::warning('CreateLedgerReversalOnOrderRefunded: missing ledger accounts', ['ledger_id' => $ledger->id]);
            return;
        }

        $totalCents = (int) $order->total_cents;
        if ($totalCents <= 0) {
            return;
        }
        $revenueCents = $order->subtotal_cents - (int) ($order->discount_total_cents ?? 0);
        $taxCents = (int) $order->tax_total_cents;
        $revenueRatio = $revenueCents / $totalCents;
        $taxRatio = $taxCents / $totalCents;
        $revDebit = (int) round($refundCents * $revenueRatio);
        $taxDebit = (int) round($refundCents * $taxRatio);
        if ($revDebit + $taxDebit !== $refundCents) {
            $revDebit = $refundCents - $taxDebit;
        }

        $entries = [
            ['account_id' => $cash->id, 'type' => 'credit', 'amount_cents' => $refundCents, 'currency' => $order->currency, 'memo' => 'Refund'],
            ['account_id' => $rev->id, 'type' => 'debit', 'amount_cents' => $revDebit, 'currency' => $order->currency, 'memo' => 'Revenue reversal'],
            ['account_id' => $tax->id, 'type' => 'debit', 'amount_cents' => $taxDebit, 'currency' => $order->currency, 'memo' => 'Tax reversal'],
        ];

        $tx = $this->ledgerService->createTransaction(
            $ledger->id,
            'refund',
            $order->id,
            'Refund: ' . $order->order_number,
            $entries
        );
        Cache::put($idempotencyKey, true, self::IDEMPOTENCY_TTL_SECONDS);
        Instrumentation::refundProcessed((string) $tenantId, $order->id, $refundCents);
        Log::channel('stack')->info('ledger_transaction_created', [
            'tenant_id' => $tenantId,
            'order_id' => $order->operational_order_id,
            'financial_order_id' => $order->id,
            'ledger_transaction_id' => $tx->id,
        ]);
    }
}
