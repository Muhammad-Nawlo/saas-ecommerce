<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderLocked;
use App\Events\Financial\OrderPaid;
use App\Events\Financial\OrderRefunded;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use Illuminate\Support\Facades\Request;

/**
 * AuditLogOrderStatusListener (Event Subscriber)
 *
 * Writes structured tenant audit log entries for OrderLocked, OrderPaid, OrderRefunded. Does not write
 * financial data; audit only. Runs synchronously so tenant context is available for AuditLogger.
 *
 * Who dispatches: OrderLocked from OrderLockService; OrderPaid from SyncFinancialOrderOnPaymentSucceededListener;
 * OrderRefunded from refund flow.
 *
 * Assumes tenant context. Writes tenant_audit_logs (tenant DB).
 */
class AuditLogOrderStatusListener
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function handleOrderLocked(OrderLocked $event): void
    {
        $this->auditLogger->logStructuredTenantAction(
            'order_locked',
            'Financial order locked: ' . $event->order->order_number,
            $event->order,
            ['status' => 'draft'],
            ['status' => 'pending'],
            ['ip' => Request::ip(), 'user_agent' => Request::userAgent()],
        );
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        $this->auditLogger->logStructuredTenantAction(
            'order_paid',
            'Financial order paid: ' . $event->order->order_number,
            $event->order,
            ['status' => 'pending'],
            ['status' => 'paid'],
            ['provider_reference' => $event->providerReference, 'ip' => Request::ip()],
        );
    }

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $this->auditLogger->logStructuredTenantAction(
            'order_refunded',
            'Financial order refunded: ' . $event->order->order_number . ' (' . $event->amountCents . ' cents)',
            $event->order,
            ['status' => $event->order->getOriginal('status') ?? 'paid'],
            ['status' => 'refunded'],
            ['amount_cents' => $event->amountCents, 'provider_reference' => $event->providerReference, 'ip' => Request::ip()],
        );
    }

    public function subscribe(object $events): array
    {
        return [
            OrderLocked::class => 'handleOrderLocked',
            OrderPaid::class => 'handleOrderPaid',
            OrderRefunded::class => 'handleOrderRefunded',
        ];
    }
}
