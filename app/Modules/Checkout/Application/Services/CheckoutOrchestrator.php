<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Services;

use App\Modules\Checkout\Application\Contracts\CartService;
use App\Modules\Checkout\Application\Contracts\InventoryService;
use App\Modules\Checkout\Application\Contracts\OrderService;
use App\Modules\Checkout\Application\Contracts\PaymentService;
use App\Modules\Checkout\Application\DTOs\CheckoutResponseDTO;
use App\Modules\Checkout\Application\Exceptions\CheckoutFailedException;
use App\Modules\Checkout\Application\Exceptions\EmptyCartException;
use App\Modules\Checkout\Application\Exceptions\PaymentInitializationException;
use App\Modules\Checkout\Application\Exceptions\StockValidationException;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Throwable;

final readonly class CheckoutOrchestrator
{
    public function __construct(
        private CartService $cartService,
        private InventoryService $inventoryService,
        private OrderService $orderService,
        private PaymentService $paymentService,
        private TransactionManager $transactionManager
    ) {
    }

    public function checkout(\App\Modules\Checkout\Application\Commands\CheckoutCartCommand $command): CheckoutResponseDTO
    {
        $cart = $this->cartService->getActiveCart($command->cartId);
        if ($cart === null) {
            throw CheckoutFailedException::because('Cart not found or not active');
        }
        if ($cart->customerEmail === null || trim($cart->customerEmail) === '') {
            throw CheckoutFailedException::because('Cart must have customer email to checkout');
        }
        if (count($cart->items) === 0) {
            throw EmptyCartException::forCart($command->cartId);
        }

        $itemsForStock = $cart->itemsForStock();
        $this->inventoryService->validateStock($itemsForStock);

        $reserved = false;
        try {
            $this->transactionManager->run(function () use ($itemsForStock): void {
                $this->inventoryService->reserveStock($itemsForStock);
            });
            $reserved = true;

            return $this->transactionManager->run(function () use ($command, $cart): CheckoutResponseDTO {
                $cartData = [
                    'tenant_id' => $cart->tenantId,
                    'customer_email' => $cart->customerEmail,
                    'items' => $cart->itemsForOrder(),
                    'customer_id' => $command->customerId,
                ];
                $orderId = $this->orderService->createOrderFromCart($cartData);

                $amount = Money::fromMinorUnits($cart->totalAmountMinorUnits, $cart->currency);
                $paymentResult = $this->paymentService->createPayment(
                    $orderId,
                    $amount,
                    $command->paymentProvider
                );

                $this->cartService->markCartConverted($command->cartId, $orderId);

                return new CheckoutResponseDTO(
                    orderId: $orderId,
                    paymentId: $paymentResult['payment_id'],
                    clientSecret: $paymentResult['client_secret'],
                    amount: $cart->totalAmountMinorUnits,
                    currency: $cart->currency
                );
            });
        } catch (StockValidationException $e) {
            throw $e;
        } catch (PaymentInitializationException $e) {
            if ($reserved) {
                $this->inventoryService->releaseStock($itemsForStock);
            }
            throw $e;
        } catch (Throwable $e) {
            if ($reserved) {
                $this->inventoryService->releaseStock($itemsForStock);
            }
            throw CheckoutFailedException::because($e->getMessage());
        }
    }

    public function confirmPayment(\App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $this->paymentService->confirmPayment($command->paymentId, $command->providerPaymentId);
        });
    }
}
