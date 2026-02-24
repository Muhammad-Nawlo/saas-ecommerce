<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use App\Modules\Inventory\Domain\ValueObjects\StockItemId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class StockReserved implements DomainEvent
{
    public function __construct(
        public StockItemId $stockItemId,
        public int $amount,
        public int $newReservedQuantity,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
