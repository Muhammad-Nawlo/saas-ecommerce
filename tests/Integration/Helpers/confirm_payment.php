<?php

return function (string $paymentId): void {
    $handler = app(\App\Modules\Checkout\Application\Handlers\ConfirmCheckoutPaymentHandler::class);
    $handler(new \App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand(
        paymentId: $paymentId,
        providerPaymentId: ''
    ));
};
