<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Handlers;

use App\Modules\Inventory\Application\Commands\CreateStockCommand;
use App\Modules\Inventory\Domain\Entities\StockItem;
use App\Modules\Inventory\Domain\Exceptions\StockAlreadyExistsException;
use App\Modules\Inventory\Domain\Repositories\StockItemRepository;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;
use App\Modules\Inventory\Domain\ValueObjects\Quantity;
use App\Modules\Inventory\Domain\ValueObjects\StockItemId;
use App\Modules\Inventory\Domain\ValueObjects\Threshold;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CreateStockHandler
{
    public function __construct(
        private StockItemRepository $stockItemRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CreateStockCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $productId = ProductId::fromString($command->productId);
            $existing = $this->stockItemRepository->findByProductId($productId);
            if ($existing !== null) {
                throw StockAlreadyExistsException::forProduct($command->productId);
            }
            $id = StockItemId::generate();
            $tenantId = TenantId::fromString($command->tenantId);
            $quantity = Quantity::fromInt($command->quantity);
            $threshold = Threshold::fromInt($command->lowStockThreshold);
            $stock = StockItem::create($id, $tenantId, $productId, $quantity, $threshold);
            $this->stockItemRepository->save($stock);
        });
    }
}
