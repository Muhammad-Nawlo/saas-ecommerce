<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Api\Controllers;

use App\Modules\Orders\Application\Commands\AddOrderItemCommand;
use App\Modules\Orders\Application\Commands\CancelOrderCommand;
use App\Modules\Orders\Application\Commands\ConfirmOrderCommand;
use App\Modules\Orders\Application\Commands\CreateOrderCommand;
use App\Modules\Orders\Application\Commands\MarkOrderPaidCommand;
use App\Modules\Orders\Application\Commands\ShipOrderCommand;
use App\Modules\Orders\Application\DTOs\OrderDTO;
use App\Modules\Orders\Application\Handlers\AddOrderItemHandler;
use App\Modules\Orders\Application\Handlers\CancelOrderHandler;
use App\Modules\Orders\Application\Handlers\ConfirmOrderHandler;
use App\Modules\Orders\Application\Handlers\CreateOrderHandler;
use App\Modules\Orders\Application\Handlers\MarkOrderPaidHandler;
use App\Modules\Orders\Application\Handlers\ShipOrderHandler;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Orders\Http\Api\Requests\AddOrderItemRequest;
use App\Modules\Orders\Http\Api\Requests\CreateOrderRequest;
use App\Modules\Orders\Http\Api\Resources\OrderResource;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class OrderController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private CreateOrderHandler $createOrderHandler,
        private AddOrderItemHandler $addOrderItemHandler,
        private ConfirmOrderHandler $confirmOrderHandler,
        private MarkOrderPaidHandler $markOrderPaidHandler,
        private ShipOrderHandler $shipOrderHandler,
        private CancelOrderHandler $cancelOrderHandler
    ) {
    }

    public function store(CreateOrderRequest $request): OrderResource|JsonResponse
    {
        $tenant = tenant();
        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant context required'], Response::HTTP_FORBIDDEN);
        }
        $command = new CreateOrderCommand(
            tenantId: (string) $tenant->getTenantKey(),
            customerEmail: $request->validated('customer_email')
        );
        $orderId = ($this->createOrderHandler)($command);
        $order = $this->orderRepository->findById($orderId);
        assert($order !== null);
        return (new OrderResource(OrderDTO::fromOrder($order)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $orderId): OrderResource|JsonResponse
    {
        try {
            $id = OrderId::fromString($orderId);
        } catch (InvalidValueObject) {
            return new JsonResponse(['message' => 'Invalid order ID'], Response::HTTP_NOT_FOUND);
        }
        $order = $this->orderRepository->findById($id);
        if ($order === null) {
            return new JsonResponse(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }
        return new OrderResource(OrderDTO::fromOrder($order));
    }

    public function addItem(AddOrderItemRequest $request, string $orderId): OrderResource|JsonResponse
    {
        $command = new AddOrderItemCommand(
            orderId: $orderId,
            productId: $request->validated('product_id'),
            quantity: (int) $request->validated('quantity'),
            unitPriceMinorUnits: (int) $request->validated('unit_price_minor_units'),
            currency: $request->validated('currency')
        );
        try {
            ($this->addOrderItemHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));
        assert($order !== null);
        return new OrderResource(OrderDTO::fromOrder($order));
    }

    public function confirm(string $orderId): OrderResource|JsonResponse
    {
        $command = new ConfirmOrderCommand(orderId: $orderId);
        try {
            ($this->confirmOrderHandler)($command);
        } catch (DomainException $e) {
            $code = str_contains($e->getMessage(), 'Insufficient stock') ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_NOT_FOUND;
            return new JsonResponse(['message' => $e->getMessage()], $code);
        }
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));
        assert($order !== null);
        return new OrderResource(OrderDTO::fromOrder($order));
    }

    public function pay(string $orderId): OrderResource|JsonResponse
    {
        $command = new MarkOrderPaidCommand(orderId: $orderId);
        try {
            ($this->markOrderPaidHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));
        assert($order !== null);
        return new OrderResource(OrderDTO::fromOrder($order));
    }

    public function ship(string $orderId): OrderResource|JsonResponse
    {
        $command = new ShipOrderCommand(orderId: $orderId);
        try {
            ($this->shipOrderHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));
        assert($order !== null);
        return new OrderResource(OrderDTO::fromOrder($order));
    }

    public function cancel(string $orderId): OrderResource|JsonResponse
    {
        $command = new CancelOrderCommand(orderId: $orderId);
        try {
            ($this->cancelOrderHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));
        assert($order !== null);
        return new OrderResource(OrderDTO::fromOrder($order));
    }
}
