<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Handlers;

use App\Modules\Inventory\Application\Commands\IncreaseStockCommand;
use App\Modules\Inventory\Domain\Repositories\StockItemRepository;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class IncreaseStockHandler
{
    public function __construct(
        private StockItemRepository $stockItemRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(IncreaseStockCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $productId = ProductId::fromString($command->productId);
            $stock = $this->stockItemRepository->findByProductId($productId);
            if ($stock === null) {
                throw new DomainException('Stock item not found for product');
            }
            $stock->increase($command->amount);
            $this->stockItemRepository->save($stock);
        });
    }
}
