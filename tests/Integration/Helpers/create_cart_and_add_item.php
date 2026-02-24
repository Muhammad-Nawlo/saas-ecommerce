<?php

return function (string $tenantId, string $productId): array {
    $createHandler = app(\App\Modules\Cart\Application\Handlers\CreateCartHandler::class);
    $addHandler = app(\App\Modules\Cart\Application\Handlers\AddItemToCartHandler::class);
    $cartId = $createHandler(new \App\Modules\Cart\Application\Commands\CreateCartCommand(
        tenantId: $tenantId,
        customerEmail: 'customer@test.com',
        sessionId: null
    ));
    $cart = app(\App\Modules\Cart\Domain\Repositories\CartRepository::class)
        ->findById($cartId);
    if ($cart === null) {
        return [];
    }
    $addHandler(new \App\Modules\Cart\Application\Commands\AddItemToCartCommand(
        cartId: $cart->id()->value(),
        productId: $productId,
        quantity: 1,
        unitPriceMinorUnits: 1000,
        currency: 'USD'
    ));
    return ['cart_id' => $cart->id()->value()];
};
