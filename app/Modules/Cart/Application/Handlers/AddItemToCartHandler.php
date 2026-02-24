<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Handlers;

use App\Modules\Cart\Application\Commands\AddItemToCartCommand;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Cart\Domain\ValueObjects\ProductId;
use App\Modules\Cart\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class AddItemToCartHandler
{
    public function __construct(
        private CartRepository $cartRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(AddItemToCartCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $cartId = CartId::fromString($command->cartId);
            $cart = $this->cartRepository->findById($cartId);
            if ($cart === null) {
                throw new DomainException('Cart not found');
            }
            $productId = ProductId::fromString($command->productId);
            $quantity = Quantity::fromInt($command->quantity);
            $unitPrice = Money::fromMinorUnits($command->unitPriceMinorUnits, $command->currency);
            $cart->addItem($productId, $quantity, $unitPrice);
            $this->cartRepository->save($cart);
        });
    }
}
