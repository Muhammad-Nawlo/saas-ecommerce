<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class OrderPaidListener implements ShouldQueue
{
    public function handle(PaymentSucceeded $event): void
    {
        Log::channel('stack')->info('Order paid', [
            'payment_id' => $event->paymentId->value(),
            'order_id' => $event->orderId,
        ]);
    }
}
