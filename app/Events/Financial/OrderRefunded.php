<?php

declare(strict_types=1);

namespace App\Events\Financial;

use App\Models\Financial\FinancialOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderRefunded Event
 *
 * Dispatched when an order (FinancialOrder) is (partially or fully) refunded.
 *
 * Listeners:
 * - CreateFinancialTransactionListener::handleOrderRefunded (creates REFUND financial_transaction)
 * - CreateLedgerReversalOnOrderRefundedListener (creates reversing ledger transaction; critical for financial integrity)
 *
 * Assumes tenant context. Amount in cents (amountCents).
 */
class OrderRefunded
{
    use Dispatchable, SerializesModels;

    /**
     * @param FinancialOrder $order The refunded financial order.
     * @param int $amountCents Refund amount in minor units.
     * @param string|null $providerReference Optional gateway reference.
     */
    public function __construct(
        public FinancialOrder $order,
        public int $amountCents,
        public ?string $providerReference = null,
    ) {}
}
