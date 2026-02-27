<?php

declare(strict_types=1);

namespace App\Listeners\Invoice;

use App\Events\Financial\OrderPaid;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * CreateInvoiceOnOrderPaidListener
 *
 * Creates draft invoice from FinancialOrder snapshot when OrderPaid is fired. Only runs when
 * config('invoicing.auto_generate_invoice_on_payment') is true. Idempotent: skips if invoice
 * already exists for the order. Uses InvoiceService::createFromOrder. Critical for invoicing integrity.
 *
 * Who dispatches OrderPaid: SyncFinancialOrderOnPaymentSucceededListener (after payment confirmation) and
 * order payment flow that marks FinancialOrder paid.
 *
 * Assumes tenant context. Writes invoices, invoice_items (tenant DB).
 */
class CreateInvoiceOnOrderPaidListener
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    /**
     * Create draft invoice from event order snapshot when auto_generate is enabled and no invoice exists.
     *
     * @param OrderPaid $event
     * @return void
     * @throws \Throwable Re-throws from InvoiceService::createFromOrder.
     * Side effects: Writes Invoice, InvoiceItem; log. Requires tenant context.
     */
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
            \Illuminate\Support\Facades\Log::channel('stack')->info('invoice_issued', [
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
