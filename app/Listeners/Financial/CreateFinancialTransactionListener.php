<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderLocked;
use App\Events\Financial\OrderPaid;
use App\Events\Financial\OrderRefunded;
use App\Models\Financial\FinancialTransaction;
use Illuminate\Support\Facades\DB;

class CreateFinancialTransactionListener
{
    public function handleOrderPaid(OrderPaid $event): void
    {
        $order = $event->order;
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

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $order = $event->order;
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
