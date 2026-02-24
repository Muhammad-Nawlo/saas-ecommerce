<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Domain\ValueObjects\ProductDescription;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Catalog\Domain\ValueObjects\ProductName;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\Slug;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Illuminate\Database\Eloquent\Model;

final class EloquentProductRepository implements ProductRepository
{
    private const MODEL_CLASS = ProductModel::class;

    public function __construct(
        private TransactionManager $transactionManager,
        private ?EventBus $eventBus = null
    ) {
    }

    public function save(Product $product): void
    {
        $this->transactionManager->run(function () use ($product): void {
            $tenantId = $this->currentTenantId();
            $modelClass = self::MODEL_CLASS;
            $existing = $modelClass::forTenant($tenantId)->find($product->id()->value());
            $model = $existing ?? new ProductModel();
            $model->id = $product->id()->value();
            $model->tenant_id = $tenantId;
            $model->name = $product->name()->value();
            $model->slug = $product->slug()->value();
            $model->description = $product->description()->value();
            $model->price_minor_units = $product->price()->amountInMinorUnits();
            $model->currency = $product->price()->currency();
            $model->is_active = $product->isActive();
            $model->created_at = $product->createdAt();
            $model->save();
            foreach ($product->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    public function findById(ProductId $id): ?Product
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $model = $modelClass::forTenant($tenantId)->find($id->value());
        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findBySlug(Slug $slug): ?Product
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $model = $modelClass::forTenant($tenantId)->where('slug', $slug->value())->first();
        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listForCurrentTenant(): array
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $models = $modelClass::forTenant($tenantId)->orderBy('created_at', 'desc')->get();
        $products = [];
        foreach ($models as $model) {
            $products[] = $this->toDomain($model);
        }
        return $products;
    }

    public function countForCurrentTenant(): int
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        return $modelClass::forTenant($tenantId)->count();
    }

    public function delete(Product $product): void
    {
        $this->transactionManager->run(function () use ($product): void {
            $model = $this->toModel($product);
            $model->delete();
            foreach ($product->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    private function toDomain(Model $model): Product
    {
        assert($model instanceof ProductModel);
        $createdAt = $model->created_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->created_at)
            : new \DateTimeImmutable($model->created_at);
        return Product::reconstitute(
            ProductId::fromString($model->id),
            TenantId::fromString($model->tenant_id),
            ProductName::fromString($model->name),
            Slug::fromString($model->slug),
            $model->description !== '' ? ProductDescription::fromString($model->description) : ProductDescription::empty(),
            Money::fromMinorUnits($model->price_minor_units, $model->currency),
            $model->is_active,
            $createdAt
        );
    }

    private function toModel(Product $product): ProductModel
    {
        $model = new ProductModel();
        $model->id = $product->id()->value();
        $model->tenant_id = $this->currentTenantId();
        $model->name = $product->name()->value();
        $model->slug = $product->slug()->value();
        $model->description = $product->description()->value();
        $model->price_minor_units = $product->price()->amountInMinorUnits();
        $model->currency = $product->price()->currency();
        $model->is_active = $product->isActive();
        $model->created_at = $product->createdAt();
        return $model;
    }

    private function currentTenantId(): string
    {
        $tenant = tenant();
        if ($tenant === null) {
            throw new \RuntimeException('Tenant context is required to access products');
        }
        return (string) $tenant->getTenantKey();
    }
}
