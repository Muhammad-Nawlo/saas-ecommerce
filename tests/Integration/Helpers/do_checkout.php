<?php

return function (string $cartId, string $tenantId, string $customerEmail): array {
    $handler = app(\App\Modules\Checkout\Application\Handlers\CheckoutCartHandler::class);
    $dto = $handler(new \App\Modules\Checkout\Application\Commands\CheckoutCartCommand(
        cartId: $cartId,
        paymentProvider: 'stripe',
        customerEmail: $customerEmail
    ));
    return [
        'order_id' => $dto->orderId,
        'payment_id' => $dto->paymentId,
        'client_secret' => $dto->clientSecret,
    ];
};
