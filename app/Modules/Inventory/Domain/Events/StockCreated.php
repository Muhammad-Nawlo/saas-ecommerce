<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use App\Modules\Inventory\Domain\ValueObjects\StockItemId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final readonly class StockCreated implements DomainEvent
{
    public function __construct(
        public StockItemId $stockItemId,
        public TenantId $tenantId,
        public string $productId,
        public int $quantity,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
