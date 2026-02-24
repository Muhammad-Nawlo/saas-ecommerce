<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Events;

use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final readonly class CartCreated implements DomainEvent
{
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId,
        public ?string $customerEmail,
        public ?string $sessionId,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
