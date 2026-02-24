<?php

declare(strict_types=1);

namespace App\Providers;

use App\Landlord\Billing\Domain\Events\SubscriptionCancelled;
use App\Listeners\OrderPaidListener;
use App\Listeners\SendOrderConfirmationEmailListener;
use App\Listeners\SubscriptionCancelledListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends BaseEventServiceProvider
{
    protected $listen = [
        SubscriptionCancelled::class => [
            SubscriptionCancelledListener::class,
        ],
        \App\Events\Financial\OrderPaid::class => [
            \App\Listeners\Invoice\CreateInvoiceOnOrderPaidListener::class,
            \App\Listeners\Financial\CreateLedgerTransactionOnOrderPaidListener::class,
        ],
    ];

    public function boot(): void
    {
        Event::subscribe(\App\Listeners\Financial\CreateFinancialTransactionListener::class);
        Event::subscribe(\App\Listeners\Financial\AuditLogOrderStatusListener::class);
        Event::listen(
            \App\Events\Financial\OrderRefunded::class,
            \App\Listeners\Financial\CreateLedgerReversalOnOrderRefundedListener::class
        );

        Event::listen(
            \App\Modules\Payments\Domain\Events\PaymentSucceeded::class,
            \App\Listeners\Financial\SyncFinancialOrderOnPaymentSucceededListener::class
        );
        Event::listen(
            \App\Modules\Payments\Domain\Events\PaymentSucceeded::class,
            OrderPaidListener::class
        );
        Event::listen(
            \App\Modules\Payments\Domain\Events\PaymentSucceeded::class,
            SendOrderConfirmationEmailListener::class
        );
    }
}
