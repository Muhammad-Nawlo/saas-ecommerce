<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Api\Controllers;

use App\Modules\Cart\Application\Commands\AbandonCartCommand;
use App\Modules\Cart\Application\Commands\AddItemToCartCommand;
use App\Modules\Cart\Application\Commands\ClearCartCommand;
use App\Modules\Cart\Application\Commands\ConvertCartCommand;
use App\Modules\Cart\Application\Commands\CreateCartCommand;
use App\Modules\Cart\Application\Commands\RemoveItemFromCartCommand;
use App\Modules\Cart\Application\Commands\UpdateCartItemCommand;
use App\Modules\Cart\Application\DTOs\CartDTO;
use App\Modules\Cart\Application\Handlers\AbandonCartHandler;
use App\Modules\Cart\Application\Handlers\AddItemToCartHandler;
use App\Modules\Cart\Application\Handlers\ClearCartHandler;
use App\Modules\Cart\Application\Handlers\ConvertCartHandler;
use App\Modules\Cart\Application\Handlers\CreateCartHandler;
use App\Modules\Cart\Application\Handlers\RemoveItemFromCartHandler;
use App\Modules\Cart\Application\Handlers\UpdateCartItemHandler;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Cart\Http\Api\Requests\AddCartItemRequest;
use App\Modules\Cart\Http\Api\Requests\CreateCartRequest;
use App\Modules\Cart\Http\Api\Requests\UpdateCartItemRequest;
use App\Modules\Cart\Http\Api\Resources\CartResource;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CartController
{
    public function __construct(
        private CartRepository $cartRepository,
        private CreateCartHandler $createCartHandler,
        private AddItemToCartHandler $addItemToCartHandler,
        private UpdateCartItemHandler $updateCartItemHandler,
        private RemoveItemFromCartHandler $removeItemFromCartHandler,
        private ClearCartHandler $clearCartHandler,
        private ConvertCartHandler $convertCartHandler,
        private AbandonCartHandler $abandonCartHandler
    ) {
    }

    public function store(CreateCartRequest $request): CartResource|JsonResponse
    {
        $tenant = tenant();
        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant context required'], Response::HTTP_FORBIDDEN);
        }
        $command = new CreateCartCommand(
            tenantId: (string) $tenant->getTenantKey(),
            customerEmail: $request->validated('customer_email'),
            sessionId: $request->validated('session_id')
        );
        try {
            $cartId = ($this->createCartHandler)($command);
        } catch (\App\Modules\Cart\Domain\Exceptions\InvalidCartStateException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cart = $this->cartRepository->findById($cartId);
        assert($cart !== null);
        return (new CartResource(CartDTO::fromCart($cart)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $cartId): CartResource|JsonResponse
    {
        try {
            $id = CartId::fromString($cartId);
        } catch (InvalidValueObject) {
            return new JsonResponse(['message' => 'Invalid cart ID'], Response::HTTP_NOT_FOUND);
        }
        $cart = $this->cartRepository->findById($id);
        if ($cart === null) {
            return new JsonResponse(['message' => 'Cart not found'], Response::HTTP_NOT_FOUND);
        }
        return new CartResource(CartDTO::fromCart($cart));
    }

    public function addItem(AddCartItemRequest $request, string $cartId): CartResource|JsonResponse
    {
        $command = new AddItemToCartCommand(
            cartId: $cartId,
            productId: $request->validated('product_id'),
            quantity: (int) $request->validated('quantity'),
            unitPriceMinorUnits: (int) $request->validated('unit_price_minor_units'),
            currency: $request->validated('currency')
        );
        try {
            ($this->addItemToCartHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cart = $this->cartRepository->findById(CartId::fromString($cartId));
        assert($cart !== null);
        return new CartResource(CartDTO::fromCart($cart));
    }

    public function updateItem(UpdateCartItemRequest $request, string $cartId, string $productId): CartResource|JsonResponse
    {
        $command = new UpdateCartItemCommand(
            cartId: $cartId,
            productId: $productId,
            quantity: (int) $request->validated('quantity')
        );
        try {
            ($this->updateCartItemHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cart = $this->cartRepository->findById(CartId::fromString($cartId));
        assert($cart !== null);
        return new CartResource(CartDTO::fromCart($cart));
    }

    public function removeItem(string $cartId, string $productId): CartResource|JsonResponse
    {
        $command = new RemoveItemFromCartCommand(cartId: $cartId, productId: $productId);
        try {
            ($this->removeItemFromCartHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cart = $this->cartRepository->findById(CartId::fromString($cartId));
        assert($cart !== null);
        return new CartResource(CartDTO::fromCart($cart));
    }

    public function clear(string $cartId): CartResource|JsonResponse
    {
        $command = new ClearCartCommand(cartId: $cartId);
        try {
            ($this->clearCartHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cart = $this->cartRepository->findById(CartId::fromString($cartId));
        assert($cart !== null);
        return new CartResource(CartDTO::fromCart($cart));
    }

    public function convert(string $cartId): JsonResponse
    {
        $command = new ConvertCartCommand(cartId: $cartId);
        try {
            $orderId = ($this->convertCartHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new JsonResponse(['order_id' => $orderId], Response::HTTP_OK);
    }

    public function abandon(string $cartId): CartResource|JsonResponse
    {
        $command = new AbandonCartCommand(cartId: $cartId);
        try {
            ($this->abandonCartHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cart = $this->cartRepository->findById(CartId::fromString($cartId));
        assert($cart !== null);
        return new CartResource(CartDTO::fromCart($cart));
    }
}
