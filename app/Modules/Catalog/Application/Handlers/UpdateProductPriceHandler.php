<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Handlers;

use App\Modules\Catalog\Application\Commands\UpdateProductPriceCommand;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class UpdateProductPriceHandler
{
    public function __construct(
        private ProductRepository $productRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(UpdateProductPriceCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $productId = ProductId::fromString($command->productId);
            $product = $this->productRepository->findById($productId);
            if ($product === null) {
                throw new DomainException('Product not found');
            }
            $newPrice = Money::fromMinorUnits($command->priceMinorUnits, $command->currency);
            $product->changePrice($newPrice);
            $this->productRepository->save($product);
        });
    }
}
