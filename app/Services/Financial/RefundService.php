<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Events\Financial\OrderRefunded;
use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialTransaction;
use App\Models\Refund\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Refund an order. Validates refundable amount, prevents over-refund, creates Refund record and
 * financial transaction, dispatches OrderRefunded (ledger reversal and invoice adjustment in listeners).
 */
final class RefundService
{
    public function refund(
        FinancialOrder $order,
        int $amountCents,
        ?string $providerReference = null,
        ?string $reason = null
    ): Refund {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Refund amount must be positive.');
        }

        $paidTotal = (int) $order->transactions()
            ->where('type', FinancialTransaction::TYPE_CREDIT)
            ->where('status', FinancialTransaction::STATUS_COMPLETED)
            ->sum('amount_cents');
        $refundedTotal = (int) $order->transactions()
            ->where('type', FinancialTransaction::TYPE_REFUND)
            ->where('status', FinancialTransaction::STATUS_COMPLETED)
            ->sum('amount_cents');
        $refundable = $paidTotal - $refundedTotal;

        if ($amountCents > $refundable) {
            throw new InvalidArgumentException(
                'Refund amount (' . $amountCents . ') cannot exceed paid amount (' . $refundable . ').'
            );
        }

        return DB::transaction(function () use ($order, $amountCents, $providerReference, $reason): Refund {
            $refund = Refund::create([
                'tenant_id' => $order->tenant_id,
                'financial_order_id' => $order->id,
                'amount_cents' => $amountCents,
                'currency' => $order->currency,
                'reason' => $reason,
                'status' => Refund::STATUS_PENDING,
                'payment_reference' => $providerReference,
            ]);

            $tx = FinancialTransaction::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_REFUND,
                'amount_cents' => $amountCents,
                'currency' => $order->currency,
                'provider_reference' => $providerReference,
                'status' => FinancialTransaction::STATUS_COMPLETED,
                'meta' => ['event' => 'order_refunded', 'refund_id' => $refund->id],
            ]);

            $refund->update(['status' => Refund::STATUS_COMPLETED, 'financial_transaction_id' => $tx->id]);
            $order->status = FinancialOrder::STATUS_REFUNDED;
            $order->save();

            Log::channel('stack')->info('refund_processed', [
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->operational_order_id,
                'financial_order_id' => $order->id,
                'refund_id' => $refund->id,
            ]);

            event(new OrderRefunded($order, $amountCents, $providerReference));
            return $refund->fresh();
        });
    }
}
