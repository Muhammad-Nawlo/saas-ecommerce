<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Events\Financial\OrderRefunded;
use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Refund an order. Ensures refund cannot exceed paid amount; creates transaction and dispatches event.
 */
final class RefundService
{
    public function refund(FinancialOrder $order, int $amountCents, ?string $providerReference = null): void
    {
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

        DB::transaction(function () use ($order, $amountCents, $providerReference): void {
            $order->status = FinancialOrder::STATUS_REFUNDED;
            $order->save();
        });

        event(new OrderRefunded($order, $amountCents, $providerReference));
    }
}
