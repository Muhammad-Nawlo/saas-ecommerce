<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Handlers;

use App\Modules\Orders\Application\Commands\ConfirmOrderCommand;
use App\Modules\Orders\Application\Services\InventoryService;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class ConfirmOrderHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private InventoryService $inventoryService,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(ConfirmOrderCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $orderId = OrderId::fromString($command->orderId);
            $order = $this->orderRepository->findById($orderId);
            if ($order === null) {
                throw new DomainException('Order not found');
            }
            foreach ($order->items() as $item) {
                $productId = $item->productId()->value();
                $required = $item->quantity();
                $available = $this->inventoryService->getAvailableQuantity($productId);
                if ($available < $required) {
                    throw new DomainException(
                        sprintf('Insufficient stock for product %s: required %d, available %d', $productId, $required, $available)
                    );
                }
            }
            foreach ($order->items() as $item) {
                $this->inventoryService->reserve($item->productId()->value(), $item->quantity());
            }
            $order->confirm();
            $this->orderRepository->save($order);
        });
    }
}
