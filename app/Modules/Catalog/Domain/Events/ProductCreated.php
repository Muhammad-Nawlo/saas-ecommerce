<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final readonly class ProductCreated implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public string $name,
        public string $slug,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
