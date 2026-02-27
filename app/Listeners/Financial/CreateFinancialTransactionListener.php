<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderLocked;
use App\Events\Financial\OrderPaid;
use App\Events\Financial\OrderRefunded;
use App\Models\Financial\FinancialTransaction;
use Illuminate\Support\Facades\DB;

/**
 * CreateFinancialTransactionListener (Event Subscriber)
 *
 * Creates FinancialTransaction records on OrderPaid (TYPE_CREDIT, amount = order total) and OrderRefunded (TYPE_REFUND).
 * Idempotent: skips if matching transaction already exists. Runs inside DB transaction. Critical for financial
 * reconciliation (sum of CREDIT transactions must equal order total for paid orders).
 *
 * Who dispatches: OrderPaid from SyncFinancialOrderOnPaymentSucceededListener / payment confirmation flow;
 * OrderRefunded from refund flow.
 *
 * Assumes tenant context. Writes financial_transactions (tenant DB).
 */
class CreateFinancialTransactionListener
{
    /**
     * Create CREDIT financial_transaction when order is paid. Idempotent.
     *
     * @param OrderPaid $event
     * @return void
     * Side effects: Writes FinancialTransaction (tenant DB). Requires tenant context.
     */
    public function handleOrderPaid(OrderPaid $event): void
    {
        $order = $event->order;
        $exists = FinancialTransaction::where('order_id', $order->id)
            ->where('type', FinancialTransaction::TYPE_CREDIT)
            ->where('status', FinancialTransaction::STATUS_COMPLETED)
            ->exists();
        if ($exists) {
            return;
        }
        DB::transaction(function () use ($order, $event): void {
            FinancialTransaction::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_CREDIT,
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
                'provider_reference' => $event->providerReference,
                'status' => FinancialTransaction::STATUS_COMPLETED,
                'meta' => ['event' => 'order_paid'],
            ]);
        });
    }

    /**
     * Create REFUND financial_transaction when order is refunded. Idempotent.
     *
     * @param OrderRefunded $event
     * @return void
     * Side effects: Writes FinancialTransaction (tenant DB). Requires tenant context.
     */
    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $order = $event->order;
        $exists = FinancialTransaction::where('order_id', $order->id)
            ->where('type', FinancialTransaction::TYPE_REFUND)
            ->where('amount_cents', $event->amountCents)
            ->where('status', FinancialTransaction::STATUS_COMPLETED)
            ->exists();
        if ($exists) {
            return;
        }
        DB::transaction(function () use ($order, $event): void {
            FinancialTransaction::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_REFUND,
                'amount_cents' => $event->amountCents,
                'currency' => $order->currency,
                'provider_reference' => $event->providerReference,
                'status' => FinancialTransaction::STATUS_COMPLETED,
                'meta' => ['event' => 'order_refunded'],
            ]);
        });
    }

    public function subscribe(object $events): array
    {
        return [
            OrderPaid::class => 'handleOrderPaid',
            OrderRefunded::class => 'handleOrderRefunded',
        ];
    }
}
