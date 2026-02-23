<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class ProductDeactivated implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
