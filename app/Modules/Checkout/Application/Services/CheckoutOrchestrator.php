<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Services;

use App\Modules\Checkout\Application\Contracts\CartService;

/**
 * CheckoutOrchestrator
 *
 * Coordinates the full checkout process:
 * - Validates cart (exists, has email, has items)
 * - Validates stock (InventoryService)
 * - Reserves or allocates inventory (simple reserve or multi-location allocation via tenant_feature)
 * - Creates Order from cart (OrderService)
 * - Applies promotions (PromotionResolverService, PromotionEvaluationService) and updates order totals
 * - Creates Payment (PaymentService) and returns client secret for Stripe
 * - Marks cart converted (CartService)
 *
 * This class acts as an application service. Used by CheckoutController (API).
 *
 * Assumes tenant context is already initialized (e.g. by route middleware).
 *
 * Side effects:
 * - Writes Order and OrderItem records (tenant DB)
 * - Writes Payment record (tenant DB)
 * - Calls Stripe (create payment intent) via PaymentService
 * - Reserves/releases inventory; may allocate when multi_location_inventory is enabled
 * - Dispatches domain events via Order/Payment modules (not directly)
 *
 * Must be executed inside DB transaction for order creation and payment confirmation.
 * Uses TransactionManager internally for checkout() and confirmPayment().
 */
use App\Modules\Checkout\Application\Contracts\InventoryService;
use App\Modules\Checkout\Application\Contracts\OrderService;
use App\Modules\Checkout\Application\DTOs\CheckoutResponseDTO;
use App\Modules\Checkout\Application\Exceptions\CheckoutFailedException;
use App\Modules\Checkout\Application\Exceptions\EmptyCartException;
use App\Modules\Checkout\Application\Exceptions\PaymentInitializationException;
use App\Modules\Checkout\Application\Exceptions\StockValidationException;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use App\Services\Inventory\InventoryAllocationService;
use App\Services\Promotion\PromotionEvaluationService;
use App\Services\Promotion\PromotionResolverService;
use Throwable;

final readonly class CheckoutOrchestrator
{
    public function __construct(
        private CartService $cartService,
        private InventoryService $inventoryService,
        private OrderService $orderService,
        private PaymentService $paymentService,
        private TransactionManager $transactionManager,
        private InventoryAllocationService $allocationService,
        private PromotionResolverService $promotionResolver,
        private PromotionEvaluationService $promotionEvaluator,
    ) {
    }

    /**
     * Run full checkout: validate cart and stock, reserve/allocate inventory, create order, apply promotions, create payment, mark cart converted.
     *
     * @param \App\Modules\Checkout\Application\Commands\CheckoutCartCommand $command Cart ID, customer ID, payment provider, optional coupon codes.
     * @return CheckoutResponseDTO Order ID, payment ID, client secret, amount, currency.
     * @throws CheckoutFailedException When cart not found, no email, or other failure.
     * @throws EmptyCartException When cart has no items.
     * @throws StockValidationException When stock insufficient.
     * @throws PaymentInitializationException When payment creation fails (releases reserved stock).
     * Side effects: Writes Order, OrderItem, Payment; reserves/allocates stock; marks cart converted. Reads central DB only via tenant_feature('multi_location_inventory'). Must run in tenant context.
     */
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

        $useMultiLocation = function_exists('tenant_feature') && (bool) tenant_feature('multi_location_inventory');
        $reserved = false;
        $orderIdForRelease = null;
        try {
            if (!$useMultiLocation) {
                $this->transactionManager->run(function () use ($itemsForStock): void {
                    $this->inventoryService->reserveStock($itemsForStock);
                });
                $reserved = true;
            }

            $result = $this->transactionManager->run(function () use ($command, $cart, $useMultiLocation, &$orderIdForRelease): CheckoutResponseDTO {
                $cartData = [
                    'tenant_id' => $cart->tenantId,
                    'customer_email' => $cart->customerEmail,
                    'items' => $cart->itemsForOrder(),
                    'user_id' => $command->customerId,
                ];
                $orderId = $this->orderService->createOrderFromCart($cartData);
                $orderIdForRelease = $orderId;

                if ($useMultiLocation) {
                    $order = OrderModel::with('items')->find($orderId);
                    if ($order !== null) {
                        $this->allocationService->allocateStock($order);
                    }
                }

                $subtotalCents = $cart->totalAmountMinorUnits;
                $itemsForEval = [];
                foreach ($cart->items as $item) {
                    $itemsForEval[] = [
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unitPriceMinorUnits,
                    ];
                }
                $couponCodes = $command->couponCodes ?? [];
                $candidates = $this->promotionResolver->getCandidates(
                    $cart->tenantId,
                    $couponCodes,
                    $command->customerId,
                    $cart->customerEmail ?? ''
                );
                $promoResult = $this->promotionEvaluator->evaluate(
                    $subtotalCents,
                    $itemsForEval,
                    $candidates,
                    $cart->currency
                );
                $discountCents = $promoResult->totalDiscountCents;
                $amountToCharge = $subtotalCents - $discountCents;

                $order = OrderModel::find($orderId);
                if ($order !== null && ($discountCents > 0 || $promoResult->appliedPromotions !== [])) {
                    $order->discount_total_cents = $discountCents;
                    $order->total_amount = $amountToCharge;
                    $order->applied_promotions = $promoResult->appliedPromotionsSnapshot();
                    $order->save();
                }

                $amount = Money::fromMinorUnits($amountToCharge, $cart->currency);
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
                    amount: $amountToCharge,
                    currency: $cart->currency
                );
            });
            return $result;
        } catch (StockValidationException $e) {
            throw $e;
        } catch (PaymentInitializationException $e) {
            if ($reserved) {
                $this->inventoryService->releaseStock($itemsForStock);
            }
            if ($useMultiLocation && $orderIdForRelease !== null) {
                $order = OrderModel::with('items')->find($orderIdForRelease);
                if ($order !== null) {
                    try {
                        $this->allocationService->releaseReservation($order);
                    } catch (Throwable) {
                    }
                }
            }
            throw $e;
        } catch (Throwable $e) {
            if ($reserved) {
                $this->inventoryService->releaseStock($itemsForStock);
            }
            if ($useMultiLocation && $orderIdForRelease !== null) {
                $order = OrderModel::with('items')->find($orderIdForRelease);
                if ($order !== null) {
                    try {
                        $this->allocationService->releaseReservation($order);
                    } catch (Throwable) {
                    }
                }
            }
            throw CheckoutFailedException::because($e->getMessage());
        }
    }

    /**
     * Confirm a checkout payment after client-side Stripe confirmation. Runs in DB transaction.
     *
     * @param \App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand $command Payment ID and provider payment ID (e.g. Stripe PI id).
     * @return void
     * @throws \Throwable Re-thrown from PaymentService (e.g. already processed).
     * Side effects: Updates payment status, may dispatch PaymentSucceeded and downstream listeners (Financial, Invoice, OrderPaid). Requires tenant context.
     */
    public function confirmPayment(\App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $this->paymentService->confirmPayment($command->paymentId, $command->providerPaymentId);
        });
    }
}
