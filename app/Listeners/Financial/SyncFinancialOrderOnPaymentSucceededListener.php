<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Services\Financial\FinancialOrderSyncService;
use App\Services\Financial\OrderLockService;
use App\Services\Financial\OrderPaymentService;
use App\Services\Financial\PaymentSnapshotService;
use App\Services\Promotion\RecordPromotionUsageService;
use App\Models\Financial\FinancialOrder;
use App\Support\Instrumentation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncFinancialOrderOnPaymentSucceededListener
 *
 * After PaymentSucceeded (Modules\Payments): syncs FinancialOrder from operational Order, locks if not locked,
 * marks paid, fills payment snapshot, records promotion usage, dispatches OrderPaid (App\Events\Financial\OrderPaid).
 * OrderPaid triggers CreateInvoiceOnOrderPaidListener, CreateLedgerTransactionOnOrderPaidListener,
 * CreateFinancialTransactionListener. Idempotent: Cache key payment_confirmed:{paymentId} (24h TTL) prevents double processing.
 *
 * Who dispatches PaymentSucceeded: Payment confirmation flow (e.g. ConfirmPaymentHandler after gateway confirm).
 *
 * Assumes tenant context. Writes financial_orders, payment snapshots; runs in DB transaction. Critical for order→financial sync.
 */
final class SyncFinancialOrderOnPaymentSucceededListener
{
    private const IDEMPOTENCY_TTL_SECONDS = 86400;
    public function __construct(
        private PaymentRepository $paymentRepository,
        private FinancialOrderSyncService $syncService,
        private OrderLockService $lockService,
        private OrderPaymentService $paymentService,
        private PaymentSnapshotService $paymentSnapshotService,
        private RecordPromotionUsageService $recordPromotionUsage,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * Sync financial order from operational order, lock if needed, mark paid, fill snapshot, record promotions, dispatch OrderPaid (inside transaction). Skips if idempotency key set or order already paid.
     *
     * @param PaymentSucceeded $event
     * @return void
     * Side effects: Writes FinancialOrder, snapshot; Cache set; dispatches OrderPaid. Requires tenant context; runs in DB transaction.
     */
    public function handle(PaymentSucceeded $event): void
    {
        $paymentId = $event->paymentId->value();
        $idempotencyKey = 'payment_confirmed:' . $paymentId;
        if (Cache::has($idempotencyKey)) {
            return;
        }

        $orderId = $event->orderId;
        $order = OrderModel::with('items')->find($orderId);
        if ($order === null) {
            Log::warning('SyncFinancialOrderOnPaymentSucceeded: operational order not found', ['order_id' => $orderId]);
            return;
        }

        $payment = $this->paymentRepository->findById(PaymentId::fromString($paymentId));
        $providerReference = $payment !== null ? ($payment->providerPaymentId() ?? '') : '';
        $tenantId = $order->tenant_id ?? (string) tenant('id');

        DB::transaction(function () use ($order, $providerReference, $payment, $tenantId, $paymentId): void {
            $existing = FinancialOrder::where('operational_order_id', $order->id)->first();
            if ($existing !== null && $existing->status === FinancialOrder::STATUS_PAID) {
                return;
            }

            $financialOrder = $this->syncService->syncFromOperationalOrder($order);
            if ($existing === null) {
                Instrumentation::orderCreated($tenantId, $order->id, $financialOrder->id);
            }
            if ($financialOrder->status === FinancialOrder::STATUS_PAID) {
                return;
            }

            if (!$financialOrder->isLocked()) {
                $this->lockService->lock($financialOrder, null, null, $order->applied_promotions ?? []);
                $financialOrder->refresh();
            }

            if ($financialOrder->status !== FinancialOrder::STATUS_PAID) {
                $this->paymentService->markPaid($financialOrder, $providerReference);
            }

            if ($payment !== null) {
                $this->paymentSnapshotService->fillSnapshot($payment, $tenantId);
            }
            $applied = $order->applied_promotions ?? [];
            if ($applied !== []) {
                $this->recordPromotionUsage->recordForOrder(
                    $order->id,
                    $order->user_id,
                    $order->customer_email,
                    $applied
                );
            }
            Log::channel('stack')->info('payment_confirmed', [
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'financial_order_id' => $financialOrder->id,
                'payment_id' => $payment?->id(),
            ]);
            $this->auditLogger->logStructuredTenantAction(
                'payment_confirmed',
                'Payment confirmed: order ' . $financialOrder->order_number,
                $financialOrder,
                ['status' => 'pending'],
                ['status' => 'paid'],
                ['payment_id' => $payment?->id()->value(), 'order_id' => $order->id],
            );
            Instrumentation::paymentConfirmed($tenantId, $paymentId, $order->id);
        });
        Cache::put('payment_confirmed:' . $paymentId, true, self::IDEMPOTENCY_TTL_SECONDS);
    }
}
