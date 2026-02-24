<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Http\Controllers;

use App\Modules\Checkout\Application\Commands\CheckoutCartCommand;
use App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand;
use App\Modules\Checkout\Application\Exceptions\CheckoutFailedException;
use App\Modules\Checkout\Application\Exceptions\EmptyCartException;
use App\Modules\Checkout\Application\Exceptions\PaymentInitializationException;
use App\Modules\Checkout\Application\Exceptions\StockValidationException;
use App\Modules\Checkout\Application\Handlers\CheckoutCartHandler;
use App\Modules\Checkout\Application\Handlers\ConfirmCheckoutPaymentHandler;
use App\Modules\Checkout\Infrastructure\Http\Requests\CheckoutRequest;
use App\Modules\Checkout\Infrastructure\Http\Requests\ConfirmCheckoutPaymentRequest;
use App\Modules\Checkout\Infrastructure\Http\Resources\CheckoutResponseResource;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutController
{
    public function __construct(
        private CheckoutCartHandler $checkoutCartHandler,
        private ConfirmCheckoutPaymentHandler $confirmCheckoutPaymentHandler
    ) {
    }

    public function checkout(CheckoutRequest $request): CheckoutResponseResource|JsonResponse
    {
        try {
            $customerId = $request->user('customer')?->getAuthIdentifier();
            $command = new CheckoutCartCommand(
                cartId: $request->validated('cart_id'),
                paymentProvider: $request->validated('payment_provider'),
                customerEmail: $request->validated('customer_email'),
                customerId: $customerId,
            );
            $dto = ($this->checkoutCartHandler)($command);
            return new CheckoutResponseResource($dto);
        } catch (EmptyCartException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (StockValidationException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (PaymentInitializationException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (CheckoutFailedException|DomainException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    public function confirmPayment(ConfirmCheckoutPaymentRequest $request): JsonResponse
    {
        try {
            $command = new ConfirmCheckoutPaymentCommand(
                paymentId: $request->validated('payment_id'),
                providerPaymentId: $request->validated('provider_payment_id', '')
            );
            ($this->confirmCheckoutPaymentHandler)($command);
            return new JsonResponse(['message' => 'Payment confirmed'], Response::HTTP_OK);
        } catch (DomainException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
