<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Events;

use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final readonly class OrderCreated implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public TenantId $tenantId,
        public string $customerEmail,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
