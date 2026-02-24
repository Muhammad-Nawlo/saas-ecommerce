<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Events;

use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Cart\Domain\ValueObjects\CartItemId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;

final readonly class CartItemAdded implements DomainEvent
{
    public function __construct(
        public CartId $cartId,
        public CartItemId $cartItemId,
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
