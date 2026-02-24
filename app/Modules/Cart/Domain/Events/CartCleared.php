<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Events;

use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class CartCleared implements DomainEvent
{
    public function __construct(
        public CartId $cartId,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
