<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationEmailListener implements ShouldQueue
{
    public function handle(object $event): void
    {
        $orderId = $event->orderId ?? null;
        Log::channel('stack')->info('Order confirmation email sent', [
            'order_id' => $orderId,
        ]);
    }
}
