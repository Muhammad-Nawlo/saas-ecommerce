<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialOrderItem;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Support\Facades\DB;

/**
 * Syncs operational order (orders table) to financial order (financial_orders).
 * Used when payment succeeds: create or reuse FinancialOrder, then lock and mark paid.
 *
 * Lifecycle: Checkout creates Order → Payment success → syncFromOperationalOrder → lock → markPaid → OrderPaid → invoice + financial_transaction.
 */
final class FinancialOrderSyncService
{
    public function syncFromOperationalOrder(OrderModel $order): FinancialOrder
    {
        return DB::transaction(function () use ($order): FinancialOrder {
            $existing = FinancialOrder::where('operational_order_id', $order->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $order->load('items');
            $subtotalCents = 0;
            foreach ($order->items as $item) {
                $subtotalCents += (int) $item->total_price_amount;
            }
            $totalCents = (int) $order->total_amount;
            $taxTotalCents = max(0, $totalCents - $subtotalCents);

            $financialOrder = FinancialOrder::create([
                'operational_order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'order_number' => 'FIN-' . $order->id,
                'currency' => $order->currency,
                'subtotal_cents' => $subtotalCents,
                'tax_total_cents' => $taxTotalCents,
                'total_cents' => $totalCents,
                'status' => FinancialOrder::STATUS_DRAFT,
            ]);

            foreach ($order->items as $item) {
                $unitCents = (int) $item->unit_price_amount;
                $qty = (int) $item->quantity;
                $subtotal = $unitCents * $qty;
                $total = (int) $item->total_price_amount;
                $taxCents = max(0, $total - $subtotal);

                FinancialOrderItem::create([
                    'order_id' => $financialOrder->id,
                    'description' => 'Product ' . $item->product_id,
                    'quantity' => $qty,
                    'unit_price_cents' => $unitCents,
                    'subtotal_cents' => $subtotal,
                    'tax_cents' => $taxCents,
                    'total_cents' => $total,
                    'metadata' => ['product_id' => $item->product_id],
                ]);
            }

            return $financialOrder->load('items');
        });
    }
}
