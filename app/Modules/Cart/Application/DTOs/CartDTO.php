<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\DTOs;

use App\Modules\Cart\Domain\Entities\Cart;
use App\Modules\Cart\Domain\Entities\CartItem;

final readonly class CartDTO
{
    /**
     * @param list<CartItemDTO> $items
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $customerEmail,
        public ?string $sessionId,
        public string $status,
        public int $totalAmountMinorUnits,
        public string $currency,
        public string $createdAt,
        public string $updatedAt,
        public array $items
    ) {
    }

    public static function fromCart(Cart $cart): self
    {
        $items = array_map(
            fn (CartItem $item) => CartItemDTO::fromCartItem($item),
            $cart->items()
        );
        return new self(
            $cart->id()->value(),
            $cart->tenantId()->value(),
            $cart->customerEmail()?->value(),
            $cart->sessionId(),
            $cart->status()->value(),
            $cart->totalAmount()->amountInMinorUnits(),
            $cart->totalAmount()->currency(),
            $cart->createdAt()->format(\DateTimeInterface::ATOM),
            $cart->updatedAt()->format(\DateTimeInterface::ATOM),
            $items
        );
    }
}
