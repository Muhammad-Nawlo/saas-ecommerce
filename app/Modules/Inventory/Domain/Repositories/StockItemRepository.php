<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Repositories;

use App\Modules\Inventory\Domain\Entities\StockItem;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;
use App\Modules\Inventory\Domain\ValueObjects\StockItemId;

interface StockItemRepository
{
    public function save(StockItem $stock): void;

    public function findById(StockItemId $id): ?StockItem;

    public function findByProductId(ProductId $productId): ?StockItem;

    public function delete(StockItem $stock): void;
}
