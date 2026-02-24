<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Services\Financial\FinancialOrderSyncService;
use App\Services\Financial\OrderLockService;
use App\Services\Financial\OrderPaymentService;
use App\Services\Financial\PaymentSnapshotService;
use App\Services\Promotion\RecordPromotionUsageService;
use App\Models\Financial\FinancialOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * After operational payment succeeds: sync financial order, lock, mark paid, fill payment snapshot.
 * Dispatches OrderPaid (financial) which triggers invoice and financial_transaction creation.
 *
 * Idempotent: if financial order already exists and is paid, skips.
 */
final class SyncFinancialOrderOnPaymentSucceededListener
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private FinancialOrderSyncService $syncService,
        private OrderLockService $lockService,
        private OrderPaymentService $paymentService,
        private PaymentSnapshotService $paymentSnapshotService,
        private RecordPromotionUsageService $recordPromotionUsage,
    ) {
    }

    public function handle(PaymentSucceeded $event): void
    {
        $orderId = $event->orderId;
        $order = OrderModel::with('items')->find($orderId);
        if ($order === null) {
            Log::warning('SyncFinancialOrderOnPaymentSucceeded: operational order not found', ['order_id' => $orderId]);
            return;
        }

        $payment = $this->paymentRepository->findById(PaymentId::fromString($event->paymentId->value()));
        $providerReference = $payment !== null ? ($payment->providerPaymentId() ?? '') : '';
        $tenantId = $order->tenant_id ?? (string) tenant('id');

        DB::transaction(function () use ($order, $providerReference, $payment, $tenantId): void {
            $existing = FinancialOrder::where('operational_order_id', $order->id)->first();
            if ($existing !== null && $existing->status === FinancialOrder::STATUS_PAID) {
                return;
            }

            $financialOrder = $this->syncService->syncFromOperationalOrder($order);
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
                    $order->customer_id,
                    $order->customer_email,
                    $applied
                );
            }
            Log::channel('stack')->info('payment_confirmed', [
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'financial_order_id' => $financialOrder->id,
            ]);
        });
    }
}
