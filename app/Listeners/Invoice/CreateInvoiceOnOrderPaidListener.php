<?php

declare(strict_types=1);

namespace App\Listeners\Invoice;

use App\Events\Financial\OrderPaid;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Log;

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
        try {
            $this->invoiceService->createFromOrder($event->order);
        } catch (\Throwable $e) {
            Log::warning('Auto-create invoice on order paid failed', [
                'order_id' => $event->order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
