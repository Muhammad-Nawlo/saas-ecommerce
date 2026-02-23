<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Api\Controllers;

use App\Modules\Catalog\Application\Commands\ActivateProductCommand;
use App\Modules\Catalog\Application\Commands\CreateProductCommand;
use App\Modules\Catalog\Application\Commands\DeactivateProductCommand;
use App\Modules\Catalog\Application\Commands\UpdateProductPriceCommand;
use App\Modules\Catalog\Application\DTOs\ProductDTO;
use App\Modules\Catalog\Application\Handlers\ActivateProductHandler;
use App\Modules\Catalog\Application\Handlers\CreateProductHandler;
use App\Modules\Catalog\Application\Handlers\DeactivateProductHandler;
use App\Modules\Catalog\Application\Handlers\UpdateProductPriceHandler;
use App\Modules\Catalog\Http\Api\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Api\Requests\UpdateProductPriceRequest;
use App\Modules\Catalog\Http\Api\Resources\ProductResource;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use App\Modules\Shared\Domain\ValueObjects\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ProductController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CreateProductHandler $createProductHandler,
        private UpdateProductPriceHandler $updateProductPriceHandler,
        private ActivateProductHandler $activateProductHandler,
        private DeactivateProductHandler $deactivateProductHandler
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $this->productRepository->listForCurrentTenant();
        $dtos = array_map(
            fn ($product) => ProductDTO::fromProduct($product),
            $products
        );
        return ProductResource::collection($dtos);
    }

    public function show(string $id): ProductResource|JsonResponse
    {
        try {
            $productId = ProductId::fromString($id);
        } catch (InvalidValueObject) {
            return new JsonResponse(['message' => 'Invalid product ID'], Response::HTTP_NOT_FOUND);
        }
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            return new JsonResponse(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }
        return new ProductResource(ProductDTO::fromProduct($product));
    }

    public function store(StoreProductRequest $request): ProductResource|JsonResponse
    {
        $tenant = tenant();
        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant context required'], Response::HTTP_FORBIDDEN);
        }
        $command = new CreateProductCommand(
            tenantId: (string) $tenant->getTenantKey(),
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            description: $request->validated('description', ''),
            priceMinorUnits: (int) $request->validated('price_minor_units'),
            currency: $request->validated('currency')
        );
        ($this->createProductHandler)($command);
        $product = $this->productRepository->findBySlug(
            Slug::fromString($request->validated('slug'))
        );
        assert($product !== null);
        return (new ProductResource(ProductDTO::fromProduct($product)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updatePrice(UpdateProductPriceRequest $request, string $id): ProductResource|JsonResponse
    {
        $command = new UpdateProductPriceCommand(
            productId: $id,
            priceMinorUnits: (int) $request->validated('price_minor_units'),
            currency: $request->validated('currency')
        );
        try {
            ($this->updateProductPriceHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $product = $this->productRepository->findById(ProductId::fromString($id));
        assert($product !== null);
        return new ProductResource(ProductDTO::fromProduct($product));
    }

    public function activate(string $id): ProductResource|JsonResponse
    {
        $command = new ActivateProductCommand(productId: $id);
        try {
            ($this->activateProductHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $product = $this->productRepository->findById(ProductId::fromString($id));
        assert($product !== null);
        return new ProductResource(ProductDTO::fromProduct($product));
    }

    public function deactivate(string $id): ProductResource|JsonResponse
    {
        $command = new DeactivateProductCommand(productId: $id);
        try {
            ($this->deactivateProductHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $product = $this->productRepository->findById(ProductId::fromString($id));
        assert($product !== null);
        return new ProductResource(ProductDTO::fromProduct($product));
    }
}
