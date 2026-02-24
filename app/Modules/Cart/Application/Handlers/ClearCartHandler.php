<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Handlers;

use App\Modules\Cart\Application\Commands\ClearCartCommand;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class ClearCartHandler
{
    public function __construct(
        private CartRepository $cartRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(ClearCartCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $cartId = CartId::fromString($command->cartId);
            $cart = $this->cartRepository->findById($cartId);
            if ($cart === null) {
                throw new DomainException('Cart not found');
            }
            $cart->clear();
            $this->cartRepository->save($cart);
        });
    }
}
