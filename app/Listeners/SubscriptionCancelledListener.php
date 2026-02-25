<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Landlord\Billing\Domain\Events\SubscriptionCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SubscriptionCancelledListener implements ShouldQueue
{
    public function handle(SubscriptionCancelled $event): void
    {
        Log::channel('stack')->info('subscription_changed', [
            'event' => 'cancelled',
            'subscription_id' => $event->subscriptionId->value(),
            'occurred_at' => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
