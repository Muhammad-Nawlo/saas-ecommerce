# Orders Module

## Purpose

Manages order lifecycle: create order, add items, confirm, mark paid, ship, cancel. Domain entities and repository live here; persistence is tenant-scoped. Consumed by Checkout (order creation), Payments (order reference on payment), and Financial/Invoice listeners (OrderPaid, OrderRefunded).

## Main Models / Entities

- **Order** (Domain entity) — Aggregate root; order number, status, totals, items.
- **OrderItem** (Domain entity) — Line item: product/sku, quantity, unit price, totals.
- **OrderModel** / **OrderItemModel** / **CustomerSummaryModel** — Eloquent persistence (tenant DB).

## Main Services

- **EloquentOrderRepository** — Implements `OrderRepository`; CRUD for orders in tenant DB.
- **LaravelOrderApplicationService** — Implements `OrderApplicationService`; dispatches commands (CreateOrder, AddOrderItem, ConfirmOrder, MarkOrderPaid, ShipOrder, CancelOrder).
- **CheckoutOrderService** (in Checkout module) — Implements OrderService; creates order from cart data (used by CheckoutOrchestrator).
- **LaravelInventoryService** (Orders/Infrastructure) — Implements InventoryService for order module (reserve/release if needed from Orders context).

## Event Flow

- **OrderCreated**, **OrderConfirmed**, **OrderPaid**, **OrderShipped**, **OrderCancelled**, **OrderItemAdded** — Domain events. OrderPaid is also emitted as `App\Events\Financial\OrderPaid` (FinancialOrder) and drives Invoice, Ledger, and Financial listeners.

## External Dependencies

- **Catalog** — Product reference on order items (read at order creation).
- **Payments** — Payment linked to order; PaymentSucceeded triggers order confirmation and OrderPaid (financial) flow.
- **Shared** — Exceptions, value objects, TransactionManager.

## Interaction With Other Modules

- **Checkout** — Creates order from cart; updates order with promotion discount and total.
- **Payments** — Order ID used for payment creation; payment confirmation triggers MarkOrderPaid and OrderPaid event.
- **Financial** — OrderPaid / OrderRefunded events trigger CreateFinancialTransaction, CreateLedgerTransaction, SyncFinancialOrder, CreateInvoiceOnOrderPaid, etc.

## Tenant Context

- **Requires tenant context.** Orders and order_items are stored in tenant DB; all handlers assume tenant is set.

## Financial Data

- **Writes operational order data only** (totals in cents, currency). FinancialOrder, FinancialTransaction, Ledger, Invoice are written by listeners and services in app/Listeners and app/Services/Financial, not by Orders module domain logic.
