<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Handlers;

use App\Modules\Orders\Application\Commands\CreateOrderCommand;
use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Domain\ValueObjects\CustomerEmail;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CreateOrderHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CreateOrderCommand $command): OrderId
    {
        $id = OrderId::generate();
        $this->transactionManager->run(function () use ($command, $id): void {
            $tenantId = TenantId::fromString($command->tenantId);
            $customerEmail = CustomerEmail::fromString($command->customerEmail);
            $order = Order::create($id, $tenantId, $customerEmail);
            $this->orderRepository->save($order);
        });
        return $id;
    }
}
