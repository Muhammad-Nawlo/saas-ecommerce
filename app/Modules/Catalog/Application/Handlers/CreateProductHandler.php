<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Handlers;

use App\Modules\Catalog\Application\Commands\CreateProductCommand;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Domain\ValueObjects\ProductDescription;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Catalog\Domain\ValueObjects\ProductName;
use App\Modules\Shared\Domain\Exceptions\PlanLimitExceededException;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\Slug;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepository $productRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CreateProductCommand $command): void
    {
        $limit = tenant_limit('products_limit');
        if ($limit !== null) {
            $currentCount = $this->productRepository->countForCurrentTenant();
            if ($currentCount >= $limit) {
                throw PlanLimitExceededException::forFeature('products_limit', $limit);
            }
        }

        $this->transactionManager->run(function () use ($command): void {
            $productId = ProductId::generate();
            $tenantId = $command->tenantIdVo();
            $name = ProductName::fromString($command->name);
            $slug = Slug::fromString($command->slug);
            $description = $command->description !== ''
                ? ProductDescription::fromString($command->description)
                : ProductDescription::empty();
            $price = Money::fromMinorUnits($command->priceMinorUnits, $command->currency);

            $product = Product::create($productId, $tenantId, $name, $slug, $description, $price);
            $product->ensureSlugUnique($this->productRepository);
            $this->productRepository->save($product);
        });
    }
}
