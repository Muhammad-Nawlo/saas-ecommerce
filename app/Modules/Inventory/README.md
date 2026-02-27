# Inventory Module

## Purpose

Manages stock levels and reservations: create stock, increase/decrease, reserve, release. Supports optional multi-location inventory when tenant has `multi_location_inventory` feature. Used by Checkout (validate, reserve/allocate, release on failure), Orders (inventory service for reserve/release), and Filament tenant admin.

## Main Models / Entities

- **StockItem** (Domain entity) — Aggregate root; product reference, quantity, low-stock threshold.
- **StockItemModel** — Eloquent persistence (tenant DB). Multi-location: InventoryLocation, InventoryLocationStock, InventoryMovement, etc. in app/Models.

## Main Services

- **EloquentStockItemRepository** — Implements StockItemRepository; persistence for stock items (tenant DB).
- **CheckoutInventoryService** (Checkout module) — Implements InventoryService for checkout: validateStock, reserveStock, releaseStock (and allocation when multi-location).
- **InventoryAllocationService** (app/Services/Inventory) — Allocates stock from locations to order; releaseReservation on failure. Used by Checkout when multi_location_inventory is enabled.

## Event Flow

- **StockCreated**, **StockIncreased**, **StockDecreased**, **StockReserved**, **StockReleased**, **LowStockReached** — Domain events (Inventory module). LowStockReached may trigger notifications; no financial writes in this module.

## External Dependencies

- **Catalog** — Product reference for stock items.
- **Shared** — Exceptions.
- **Landlord** — `tenant_feature('multi_location_inventory')` used by Checkout to decide reserve vs allocate.

## Interaction With Other Modules

- **Checkout** — Validates and reserves (or allocates) stock; releases on payment/creation failure.
- **Orders** — May use inventory service for reserve/release in order flow (LaravelInventoryService in Orders module).

## Tenant Context

- **Requires tenant context.** Stock and locations are tenant-scoped; all tables in tenant DB.

## Financial Data

- **Does not write financial data.** Tracks quantity only; no FinancialOrder, Invoice, or Ledger writes.
