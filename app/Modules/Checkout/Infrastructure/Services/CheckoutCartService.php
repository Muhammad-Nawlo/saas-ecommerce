<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Services;

use App\Modules\Cart\Application\DTOs\CartItemDTO;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Checkout\Application\Contracts\CartService;
use App\Modules\Checkout\Application\DTOs\CartSnapshotDTO;
use App\Modules\Checkout\Application\DTOs\CartSnapshotItemDTO;

final readonly class CheckoutCartService implements CartService
{
    public function __construct(
        private CartRepository $cartRepository
    ) {
    }

    public function getActiveCart(string $cartId): ?CartSnapshotDTO
    {
        $id = CartId::fromString($cartId);
        $cart = $this->cartRepository->findById($id);
        if ($cart === null || !$cart->status()->isActive()) {
            return null;
        }
        $items = [];
        foreach ($cart->items() as $item) {
            $dto = CartItemDTO::fromCartItem($item);
            $items[] = new CartSnapshotItemDTO(
                $dto->productId,
                $dto->quantity,
                $dto->unitPriceMinorUnits,
                $dto->currency
            );
        }
        return new CartSnapshotDTO(
            cartId: $cart->id()->value(),
            tenantId: $cart->tenantId()->value(),
            customerEmail: $cart->customerEmail()?->value(),
            items: $items,
            totalAmountMinorUnits: $cart->totalAmount()->amountInMinorUnits(),
            currency: $cart->totalAmount()->currency()
        );
    }

    public function markCartConverted(string $cartId, string $orderId): void
    {
        $id = CartId::fromString($cartId);
        $cart = $this->cartRepository->findById($id);
        if ($cart === null) {
            return;
        }
        $cart->markConverted($orderId);
        $this->cartRepository->save($cart);
    }
}
