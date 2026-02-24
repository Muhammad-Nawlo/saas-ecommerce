<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Handlers;

use App\Modules\Cart\Application\Commands\AbandonCartCommand;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class AbandonCartHandler
{
    public function __construct(
        private CartRepository $cartRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(AbandonCartCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $cartId = CartId::fromString($command->cartId);
            $cart = $this->cartRepository->findById($cartId);
            if ($cart === null) {
                throw new DomainException('Cart not found');
            }
            $cart->markAbandoned();
            $this->cartRepository->save($cart);
        });
    }
}
