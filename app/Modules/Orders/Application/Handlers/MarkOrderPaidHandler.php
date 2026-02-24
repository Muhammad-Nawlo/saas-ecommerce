<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Handlers;

use App\Modules\Orders\Application\Commands\MarkOrderPaidCommand;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class MarkOrderPaidHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(MarkOrderPaidCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $orderId = OrderId::fromString($command->orderId);
            $order = $this->orderRepository->findById($orderId);
            if ($order === null) {
                throw new DomainException('Order not found');
            }
            if ($order->status()->value() === 'paid') {
                return;
            }
            $order->markAsPaid();
            $this->orderRepository->save($order);
        });
    }
}
