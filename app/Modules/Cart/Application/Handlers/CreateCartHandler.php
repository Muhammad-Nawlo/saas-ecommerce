<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Handlers;

use App\Modules\Cart\Application\Commands\CreateCartCommand;
use App\Modules\Cart\Domain\Entities\Cart;
use App\Modules\Cart\Domain\Exceptions\InvalidCartStateException;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Cart\Domain\ValueObjects\CustomerEmail;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CreateCartHandler
{
    public function __construct(
        private CartRepository $cartRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CreateCartCommand $command): CartId
    {
        $customerEmail = null;
        $sessionId = $command->sessionId;
        if ($command->customerEmail !== null && trim($command->customerEmail) !== '') {
            $customerEmail = CustomerEmail::fromString($command->customerEmail);
        } elseif ($sessionId === null || trim($sessionId) === '') {
            throw InvalidCartStateException::because('Either customer_email or session_id must be provided');
        } else {
            $sessionId = trim($sessionId);
        }
        $id = CartId::generate();
        $this->transactionManager->run(function () use ($command, $id, $customerEmail, $sessionId): void {
            $tenantId = TenantId::fromString($command->tenantId);
            $cart = Cart::create($id, $tenantId, $customerEmail, $sessionId);
            $this->cartRepository->save($cart);
        });
        return $id;
    }
}
