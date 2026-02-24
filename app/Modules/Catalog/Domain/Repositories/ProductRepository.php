<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\ValueObjects\Slug;

interface ProductRepository
{
    public function save(Product $product): void;

    public function findById(ProductId $id): ?Product;

    public function findBySlug(Slug $slug): ?Product;

    /**
     * @return list<Product>
     */
    public function listForCurrentTenant(): array;

    public function countForCurrentTenant(): int;

    public function delete(Product $product): void;
}
