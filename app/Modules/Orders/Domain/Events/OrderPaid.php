<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Events;

use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class OrderPaid implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
