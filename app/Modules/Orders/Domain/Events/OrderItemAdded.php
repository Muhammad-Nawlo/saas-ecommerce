<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Events;

use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Orders\Domain\ValueObjects\OrderItemId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class OrderItemAdded implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public OrderItemId $orderItemId,
        public string $productId,
        public int $quantity,
        public int $unitPriceMinorUnits,
        public string $currency,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
