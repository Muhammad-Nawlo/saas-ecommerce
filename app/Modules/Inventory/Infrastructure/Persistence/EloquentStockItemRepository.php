<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Inventory\Domain\Entities\StockItem;
use App\Modules\Inventory\Domain\Repositories\StockItemRepository;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;
use App\Modules\Inventory\Domain\ValueObjects\StockItemId;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Illuminate\Database\Eloquent\Model;

final class EloquentStockItemRepository implements StockItemRepository
{
    private const MODEL_CLASS = StockItemModel::class;

    public function __construct(
        private TransactionManager $transactionManager,
        private ?EventBus $eventBus = null
    ) {
    }

    public function save(StockItem $stock): void
    {
        $this->transactionManager->run(function () use ($stock): void {
            $tenantId = $this->currentTenantId();
            $modelClass = self::MODEL_CLASS;
            $existing = $modelClass::forTenant($tenantId)->find($stock->id()->value());
            $model = $existing ?? new StockItemModel();
            $model->id = $stock->id()->value();
            $model->tenant_id = $tenantId;
            $model->product_id = $stock->productId()->value();
            $model->quantity = $stock->quantity();
            $model->reserved_quantity = $stock->reservedQuantity();
            $model->low_stock_threshold = $stock->lowStockThreshold();
            $model->created_at = $stock->createdAt();
            $model->save();
            foreach ($stock->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    public function findById(StockItemId $id): ?StockItem
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $model = $modelClass::forTenant($tenantId)->find($id->value());
        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByProductId(ProductId $productId): ?StockItem
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $model = $modelClass::forTenant($tenantId)->where('product_id', $productId->value())->first();
        return $model !== null ? $this->toDomain($model) : null;
    }

    public function delete(StockItem $stock): void
    {
        $this->transactionManager->run(function () use ($stock): void {
            $model = $this->toModel($stock);
            $model->delete();
            foreach ($stock->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    private function toDomain(Model $model): StockItem
    {
        assert($model instanceof StockItemModel);
        $createdAt = $model->created_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->created_at)
            : new \DateTimeImmutable($model->created_at);
        return StockItem::reconstitute(
            StockItemId::fromString($model->id),
            TenantId::fromString($model->tenant_id),
            ProductId::fromString($model->product_id),
            $model->quantity,
            $model->reserved_quantity,
            $model->low_stock_threshold,
            $createdAt
        );
    }

    private function toModel(StockItem $stock): StockItemModel
    {
        $model = new StockItemModel();
        $model->id = $stock->id()->value();
        $model->tenant_id = $this->currentTenantId();
        $model->product_id = $stock->productId()->value();
        $model->quantity = $stock->quantity();
        $model->reserved_quantity = $stock->reservedQuantity();
        $model->low_stock_threshold = $stock->lowStockThreshold();
        $model->created_at = $stock->createdAt();
        return $model;
    }

    private function currentTenantId(): string
    {
        $tenant = tenant();
        if ($tenant === null) {
            throw new \RuntimeException('Tenant context is required to access stock items');
        }
        return (string) $tenant->getTenantKey();
    }
}
