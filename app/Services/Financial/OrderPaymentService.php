<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Events\Financial\OrderPaid;
use App\Models\Financial\FinancialOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mark an order as paid and dispatch OrderPaid (listener creates financial transaction).
 */
final class OrderPaymentService
{
    public function markPaid(FinancialOrder $order, string $providerReference): void
    {
        if ($order->status === FinancialOrder::STATUS_PAID) {
            throw new InvalidArgumentException('Order is already paid.');
        }
        if (!$order->isLocked()) {
            throw new InvalidArgumentException('Order must be locked before marking as paid.');
        }

        DB::transaction(function () use ($order, $providerReference): void {
            $order->status = FinancialOrder::STATUS_PAID;
            $order->paid_at = now();
            $order->save();
        });

        event(new OrderPaid($order, $providerReference));
    }
}
