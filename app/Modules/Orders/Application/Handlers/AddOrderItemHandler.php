<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Handlers;

use App\Modules\Orders\Application\Commands\AddOrderItemCommand;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Orders\Domain\ValueObjects\ProductId;
use App\Modules\Orders\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class AddOrderItemHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(AddOrderItemCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $orderId = OrderId::fromString($command->orderId);
            $order = $this->orderRepository->findById($orderId);
            if ($order === null) {
                throw new DomainException('Order not found');
            }
            $productId = ProductId::fromString($command->productId);
            $quantity = Quantity::fromInt($command->quantity);
            $unitPrice = Money::fromMinorUnits($command->unitPriceMinorUnits, $command->currency);
            $order->addItem($productId, $quantity, $unitPrice);
            $this->orderRepository->save($order);
        });
    }
}
