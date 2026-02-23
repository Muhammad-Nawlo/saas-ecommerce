<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Handlers;

use App\Modules\Catalog\Application\Commands\DeactivateProductCommand;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class DeactivateProductHandler
{
    public function __construct(
        private ProductRepository $productRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(DeactivateProductCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $productId = ProductId::fromString($command->productId);
            $product = $this->productRepository->findById($productId);
            if ($product === null) {
                throw new DomainException('Product not found');
            }
            $product->deactivate();
            $this->productRepository->save($product);
        });
    }
}
