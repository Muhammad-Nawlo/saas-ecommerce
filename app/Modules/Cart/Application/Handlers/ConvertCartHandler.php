<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Handlers;

use App\Modules\Cart\Application\Commands\ConvertCartCommand;
use App\Modules\Cart\Application\Services\OrderCreationService;
use App\Modules\Cart\Application\Services\StockValidationService;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class ConvertCartHandler
{
    public function __construct(
        private CartRepository $cartRepository,
        private StockValidationService $stockValidationService,
        private OrderCreationService $orderCreationService,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(ConvertCartCommand $command): string
    {
        $orderId = null;
        $this->transactionManager->run(function () use ($command, &$orderId): void {
            $cartId = CartId::fromString($command->cartId);
            $cart = $this->cartRepository->findById($cartId);
            if ($cart === null) {
                throw new DomainException('Cart not found');
            }
            $customerEmail = $cart->customerEmail()?->value();
            if ($customerEmail === null || $customerEmail === '') {
                throw new DomainException('Cart must have customer email to convert to order');
            }
            $itemsForValidation = [];
            $itemsForOrder = [];
            foreach ($cart->items() as $item) {
                $itemsForValidation[] = [
                    'product_id' => $item->productId()->value(),
                    'quantity' => $item->quantity(),
                ];
                $itemsForOrder[] = [
                    'product_id' => $item->productId()->value(),
                    'quantity' => $item->quantity(),
                    'unit_price_minor_units' => $item->unitPrice()->amountInMinorUnits(),
                    'currency' => $item->unitPrice()->currency(),
                ];
            }
            $this->stockValidationService->validateForItems($itemsForValidation);
            $orderId = $this->orderCreationService->createOrderFromCart(
                $cart->tenantId()->value(),
                $customerEmail,
                $itemsForOrder
            );
            $cart->markConverted($orderId);
            $this->cartRepository->save($cart);
        });
        assert($orderId !== null);
        return $orderId;
    }
}
