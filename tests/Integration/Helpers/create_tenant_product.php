<?php

return function (string $tenantId): ?\App\Modules\Catalog\Domain\Entities\Product {
    $handler = app(\App\Modules\Catalog\Application\Handlers\CreateProductHandler::class);
    $repo = app(\App\Modules\Catalog\Domain\Repositories\ProductRepository::class);
    $slug = 'e2e-product-' . substr(md5((string) microtime()), 0, 8);
    $handler(new \App\Modules\Catalog\Application\Commands\CreateProductCommand(
        tenantId: $tenantId,
        name: 'E2E Product',
        slug: $slug,
        description: '',
        priceMinorUnits: 1000,
        currency: 'USD'
    ));
    return $repo->findBySlug(\App\Modules\Shared\Domain\ValueObjects\Slug::fromString($slug));
};
