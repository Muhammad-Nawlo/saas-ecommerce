<?php

declare(strict_types=1);

namespace App\Listeners\Financial;

use App\Events\Financial\OrderLocked;
use App\Events\Financial\OrderPaid;
use App\Events\Financial\OrderRefunded;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use Illuminate\Support\Facades\Request;

/**
 * Audit logs for order locked/paid/refunded. Runs sync so tenant context is available for logging.
 */
class AuditLogOrderStatusListener
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function handleOrderLocked(OrderLocked $event): void
    {
        $this->auditLogger->logTenantAction(
            'order_locked',
            'Financial order locked: ' . $event->order->order_number,
            $event->order,
            [
                'old_status' => 'draft',
                'new_status' => 'pending',
                'actor_id' => auth()->id(),
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        $this->auditLogger->logTenantAction(
            'order_paid',
            'Financial order paid: ' . $event->order->order_number,
            $event->order,
            [
                'old_status' => 'pending',
                'new_status' => 'paid',
                'provider_reference' => $event->providerReference,
                'actor_id' => auth()->id(),
                'ip' => Request::ip(),
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $this->auditLogger->logTenantAction(
            'order_refunded',
            'Financial order refunded: ' . $event->order->order_number . ' (' . $event->amountCents . ' cents)',
            $event->order,
            [
                'new_status' => 'refunded',
                'amount_cents' => $event->amountCents,
                'provider_reference' => $event->providerReference,
                'actor_id' => auth()->id(),
                'ip' => Request::ip(),
                'timestamp' => now()->toIso8601String(),
            ],
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
