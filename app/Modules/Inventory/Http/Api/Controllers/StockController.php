<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Api\Controllers;

use App\Modules\Inventory\Application\Commands\CreateStockCommand;
use App\Modules\Inventory\Application\Commands\DecreaseStockCommand;
use App\Modules\Inventory\Application\Commands\IncreaseStockCommand;
use App\Modules\Inventory\Application\Commands\ReleaseStockCommand;
use App\Modules\Inventory\Application\Commands\ReserveStockCommand;
use App\Modules\Inventory\Application\Commands\SetLowStockThresholdCommand;
use App\Modules\Inventory\Application\DTOs\StockItemDTO;
use App\Modules\Inventory\Application\Handlers\CreateStockHandler;
use App\Modules\Inventory\Application\Handlers\DecreaseStockHandler;
use App\Modules\Inventory\Application\Handlers\IncreaseStockHandler;
use App\Modules\Inventory\Application\Handlers\ReleaseStockHandler;
use App\Modules\Inventory\Application\Handlers\ReserveStockHandler;
use App\Modules\Inventory\Application\Handlers\SetLowStockThresholdHandler;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Exceptions\StockAlreadyExistsException;
use App\Modules\Inventory\Domain\Repositories\StockItemRepository;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;
use App\Modules\Inventory\Http\Api\Requests\CreateStockRequest;
use App\Modules\Inventory\Http\Api\Requests\DecreaseStockRequest;
use App\Modules\Inventory\Http\Api\Requests\IncreaseStockRequest;
use App\Modules\Inventory\Http\Api\Requests\ReleaseStockRequest;
use App\Modules\Inventory\Http\Api\Requests\ReserveStockRequest;
use App\Modules\Inventory\Http\Api\Requests\SetLowStockThresholdRequest;
use App\Modules\Inventory\Http\Api\Resources\StockItemResource;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StockController
{
    public function __construct(
        private StockItemRepository $stockItemRepository,
        private CreateStockHandler $createStockHandler,
        private IncreaseStockHandler $increaseStockHandler,
        private DecreaseStockHandler $decreaseStockHandler,
        private ReserveStockHandler $reserveStockHandler,
        private ReleaseStockHandler $releaseStockHandler,
        private SetLowStockThresholdHandler $setLowStockThresholdHandler
    ) {
    }

    public function store(CreateStockRequest $request): StockItemResource|JsonResponse
    {
        $tenant = tenant();
        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant context required'], Response::HTTP_FORBIDDEN);
        }
        $command = new CreateStockCommand(
            tenantId: (string) $tenant->getTenantKey(),
            productId: $request->validated('product_id'),
            quantity: (int) $request->validated('quantity'),
            lowStockThreshold: (int) $request->validated('low_stock_threshold')
        );
        try {
            ($this->createStockHandler)($command);
        } catch (StockAlreadyExistsException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
        $productId = ProductId::fromString($request->validated('product_id'));
        $stock = $this->stockItemRepository->findByProductId($productId);
        assert($stock !== null);
        return (new StockItemResource(StockItemDTO::fromStockItem($stock)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $productId): StockItemResource|JsonResponse
    {
        try {
            $pid = ProductId::fromString($productId);
        } catch (InvalidValueObject) {
            return new JsonResponse(['message' => 'Invalid product ID'], Response::HTTP_NOT_FOUND);
        }
        $stock = $this->stockItemRepository->findByProductId($pid);
        if ($stock === null) {
            return new JsonResponse(['message' => 'Stock not found for product'], Response::HTTP_NOT_FOUND);
        }
        return new StockItemResource(StockItemDTO::fromStockItem($stock));
    }

    public function increase(IncreaseStockRequest $request, string $productId): StockItemResource|JsonResponse
    {
        $command = new IncreaseStockCommand(
            productId: $productId,
            amount: (int) $request->validated('amount')
        );
        try {
            ($this->increaseStockHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $stock = $this->stockItemRepository->findByProductId(ProductId::fromString($productId));
        assert($stock !== null);
        return new StockItemResource(StockItemDTO::fromStockItem($stock));
    }

    public function decrease(DecreaseStockRequest $request, string $productId): StockItemResource|JsonResponse
    {
        $command = new DecreaseStockCommand(
            productId: $productId,
            amount: (int) $request->validated('amount')
        );
        try {
            ($this->decreaseStockHandler)($command);
        } catch (InsufficientStockException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $stock = $this->stockItemRepository->findByProductId(ProductId::fromString($productId));
        assert($stock !== null);
        return new StockItemResource(StockItemDTO::fromStockItem($stock));
    }

    public function reserve(ReserveStockRequest $request, string $productId): StockItemResource|JsonResponse
    {
        $command = new ReserveStockCommand(
            productId: $productId,
            amount: (int) $request->validated('amount')
        );
        try {
            ($this->reserveStockHandler)($command);
        } catch (InsufficientStockException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $stock = $this->stockItemRepository->findByProductId(ProductId::fromString($productId));
        assert($stock !== null);
        return new StockItemResource(StockItemDTO::fromStockItem($stock));
    }

    public function release(ReleaseStockRequest $request, string $productId): StockItemResource|JsonResponse
    {
        $command = new ReleaseStockCommand(
            productId: $productId,
            amount: (int) $request->validated('amount')
        );
        try {
            ($this->releaseStockHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $stock = $this->stockItemRepository->findByProductId(ProductId::fromString($productId));
        assert($stock !== null);
        return new StockItemResource(StockItemDTO::fromStockItem($stock));
    }

    public function setLowStockThreshold(SetLowStockThresholdRequest $request, string $productId): StockItemResource|JsonResponse
    {
        $command = new SetLowStockThresholdCommand(
            productId: $productId,
            threshold: (int) $request->validated('threshold')
        );
        try {
            ($this->setLowStockThresholdHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $stock = $this->stockItemRepository->findByProductId(ProductId::fromString($productId));
        assert($stock !== null);
        return new StockItemResource(StockItemDTO::fromStockItem($stock));
    }
}
