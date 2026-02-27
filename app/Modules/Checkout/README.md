# Checkout Module

## Purpose

Orchestrates the full checkout flow: validate cart, reserve/allocate inventory, create order from cart, apply promotions, create payment intent, and confirm payment. Single entry point for "checkout" and "confirm payment" from the tenant API.

## Main Models / Entities

- No domain entities in this module. Uses Order (Orders), Payment (Payments), Cart (Cart), and inventory/stock from other modules.

## Main Services

- **CheckoutOrchestrator** — Application service; coordinates cart validation, stock reserve/release, order creation, promotion evaluation, payment creation, cart conversion. **Must run inside DB transaction** for order+payment creation and confirm.
- **CheckoutCartService** — Adapter to Cart module (get active cart, mark converted).
- **CheckoutOrderService** — Adapter to Orders (create order from cart data).
- **CheckoutPaymentService** — Adapter to Payments (create payment, confirm payment).
- **CheckoutInventoryService** — Adapter to Inventory (validate, reserve, release stock).
- **CheckoutInventoryService** + **InventoryAllocationService** — When `multi_location_inventory` feature is enabled, allocation is used instead of simple reserve.

## Event Flow

- Checkout does not dispatch domain events itself. Order and Payment modules dispatch events (OrderCreated, PaymentSucceeded, etc.); Financial and Invoice listeners react to OrderPaid / PaymentSucceeded.

## External Dependencies

- **Cart** — CartService, OrderCreationService (CartOrderCreationService), StockValidationService.
- **Orders** — OrderService, OrderModel (read/write).
- **Payments** — PaymentService (create payment, confirm).
- **Inventory** — InventoryService (validate, reserve, release); **InventoryAllocationService** (allocate/release when multi-location enabled).
- **Promotion** — PromotionResolverService, PromotionEvaluationService (discounts, coupon codes).
- **Shared** — TransactionManager, Money, exceptions.
- **Landlord** — `tenant_feature('multi_location_inventory')` to decide reserve vs allocate.

## Interaction With Other Modules

- **Cart** — Read cart, mark converted after successful order+payment creation.
- **Orders** — Create order, update discount/total from promotions.
- **Payments** — Create payment intent, confirm payment (Stripe).
- **Inventory** — Reserve or allocate stock; release on failure.
- **Promotion** — Resolve and evaluate promotions; write discount and applied_promotions on order.

## Tenant Context

- **Requires tenant context.** All operations run in tenant DB and assume tenancy is already initialized (e.g. by middleware on API routes).

## Financial Data

- **Indirect.** Creates Order and Payment; FinancialOrder/FinancialTransaction/Ledger/Invoice are written by listeners (OrderPaid, PaymentSucceeded), not by Checkout itself. Checkout writes operational order totals (discount_total_cents, total_amount, applied_promotions).
