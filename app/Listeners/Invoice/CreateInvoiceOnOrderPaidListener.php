<?php

declare(strict_types=1);

namespace App\Listeners\Invoice;

use App\Events\Financial\OrderPaid;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * Creates invoice when financial order is marked paid. Idempotent: skips if invoice already exists.
 * Runs sync to preserve tenant context.
 */
class CreateInvoiceOnOrderPaidListener
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function handle(OrderPaid $event): void
    {
        if (!config('invoicing.auto_generate_invoice_on_payment', false)) {
            return;
        }
        if (Invoice::where('order_id', $event->order->id)->exists()) {
            return;
        }
        try {
            $invoice = $this->invoiceService->createFromOrder($event->order);
            \Illuminate\Support\Facades\Log::channel('stack')->info('invoice_created', [
                'tenant_id' => $event->order->tenant_id,
                'order_id' => $event->order->id,
                'financial_order_id' => $event->order->id,
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Auto-create invoice on order paid failed', [
                'order_id' => $event->order->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
