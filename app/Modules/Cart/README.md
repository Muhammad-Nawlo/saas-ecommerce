# Cart Module

## Purpose

Manages shopping cart and cart items for tenant stores. Handles create cart, add/update/remove items, clear, convert to order, and abandon. Consumed by Checkout (cart validation and conversion) and tenant API.

## Main Models / Entities

- **Cart** (Domain entity) — Aggregate root; tenant, customer email, currency, items.
- **CartItem** (Domain entity) — Line item: product id, quantity, unit price (minor units).
- **CartModel** / **CartItemModel** — Eloquent persistence (tenant DB).

## Main Services

- **EloquentCartRepository** — Implements `CartRepository`; CRUD for carts and items in tenant DB.
- **CartOrderCreationService** — Implements `OrderCreationService`; used by Checkout to create order from cart data.
- **CartStockValidationService** — Implements `StockValidationService`; validates stock availability for cart items (used by Checkout).

## Event Flow

- **CartCreated**, **CartItemAdded**, **CartItemUpdated**, **CartItemRemoved**, **CartCleared**, **CartConverted**, **CartAbandoned** — Domain events emitted by handlers; used for analytics or side effects (no financial writes in this module).

## External Dependencies

- **Catalog** — Product lookup for validation and display (read-only).
- **Orders** — Order creation contract (`OrderCreationService`) implemented by Cart module.
- **Shared** — Exceptions, value objects.

## Interaction With Other Modules

- **Checkout** — Uses CartService to get active cart, validate, mark converted; uses CartOrderCreationService (OrderCreationService) to create order from cart.
- **Orders** — Receives cart-derived order data from Checkout (not directly from Cart module in same flow).

## Tenant Context

- **Requires tenant context.** Carts and cart_items are tenant-scoped; repository uses tenant DB.

## Financial Data

- **Does not write financial data.** Stores amounts in minor units for display and for passing to Checkout/Order; no FinancialOrder, Invoice, or Ledger writes.
