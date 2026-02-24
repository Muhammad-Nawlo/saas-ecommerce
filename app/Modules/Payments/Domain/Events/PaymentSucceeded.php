<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Events;

use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class PaymentSucceeded implements DomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
        public string $orderId,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
