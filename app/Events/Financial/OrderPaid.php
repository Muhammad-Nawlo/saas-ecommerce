<?php

declare(strict_types=1);

namespace App\Events\Financial;

use App\Models\Financial\FinancialOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderPaid Event
 *
 * Dispatched when an order (FinancialOrder) status transitions to paid (e.g. after payment confirmation).
 *
 * Listeners:
 * - CreateInvoiceOnOrderPaidListener (creates draft invoice from order snapshot)
 * - CreateLedgerTransactionOnOrderPaidListener (creates ledger transaction; critical for financial integrity)
 * - CreateFinancialTransactionListener::handleOrderPaid (creates CREDIT financial_transaction)
 *
 * Critical for financial and invoice integrity. Assumes tenant context. Dispatched from tenant context (e.g. after PaymentSucceeded and FinancialOrder sync).
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    /**
     * @param FinancialOrder $order The paid financial order (locked, status paid).
     * @param string $providerReference Gateway reference (e.g. Stripe PaymentIntent id).
     */
    public function __construct(
        public FinancialOrder $order,
        public string $providerReference,
    ) {}
}
