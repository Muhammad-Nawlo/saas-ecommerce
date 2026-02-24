# Multi-Location Inventory

Production-grade multi-location inventory for tenant stores. Transaction-safe, reservation-aware, transfer-aware, and audit-logged.

## Database (tenant)

- **inventory_locations** — id (UUID), tenant_id, name, code (unique per tenant), type (warehouse|retail_store|fulfillment_center), address (JSON), is_active, timestamps, soft deletes.
- **inventory_location_stocks** — product_id, location_id, quantity, reserved_quantity, low_stock_threshold; unique (product_id, location_id).
- **inventory_movements** — Append-only log: product_id, location_id, type (increase|decrease|reserve|release|transfer_out|transfer_in|adjustment), quantity, reference_type/id, meta (actor_id, IP, reason).
- **inventory_reservations** — product_id, location_id, order_id, quantity, expires_at.
- **inventory_transfers** — product_id, from_location_id, to_location_id, quantity, status (pending|completed|cancelled), completed_at.

## Services

- **InventoryLocationService** — create/get default location, deactivate, `canCreateMoreLocations()`, `canTransfer()` (feature-gated).
- **InventoryAllocationService** — `allocateStock(Order)`, `reserveStock()`, `releaseReservation(Order)`, `confirmReservation(Order)`, `getAvailableQuantity()`. Uses DB transactions and row locking.
- **InventoryTransferService** — `transfer(productId, from, to, quantity)` (decrease source, increase dest, movements, audit).
- **InventoryStockAdjustmentService** — `adjust(productId, locationId, delta, reason)`, `setLowStockThreshold()`; logs movement and audit.
- **InventoryMovementLogger** — Every stock change logs to inventory_movements with meta (actor_id, IP, reason).

## Order integration

- **Checkout (multi_location_inventory on):** validate stock via allocation; create order; `allocateStock(order)`; on failure `releaseReservation(order)`.
- **Checkout (off):** existing flow (reserve from stock_items, create order).
- **Confirm order (multi_location on):** `confirmReservation(order)` (decrease quantity, remove reservations).
- **Cancel order (multi_location on):** `releaseReservation(order)`.

## Feature limit

- `tenant_feature('multi_location_inventory')`: when **false**, only one location is allowed; transfer features hidden and blocked in service layer.
- When **true**, multiple locations and transfers are allowed.

## Filament (tenant)

- **InventoryLocationResource** — CRUD locations, deactivate; create disabled when `!canCreateMoreLocations()`.
- **InventoryStockResource** — List stock per product/location; actions: Adjust (delta + reason), Set low stock threshold.
- **InventoryTransferResource** — Create transfer (executes via TransferService); Cancel for pending.
- **InventoryMovementResource** — Read-only; filters: type, location, date.

## Widgets

- **TotalStockValueWidget** — Sum of (quantity × product price) across location stocks.
- **LowStockLocationsWidget** — Table of stocks where available ≤ low_stock_threshold.
- **OutOfStockProductsWidget** — Products with zero available across all locations.

## Events

- **LowStockDetected** — Dispatched when adjustment leaves available ≤ low_stock_threshold (optional notifications/dashboard).

## Audit

- location_created, stock_adjusted, transfer_completed, reservation_created, reservation_released, reservation_confirmed (via AuditLogger).

## Tests

Run: `php artisan test tests/Feature/MultiLocationInventory/`

- Create location
- Adjust stock
- Reserve stock for order
- Prevent overselling
- Transfer (when feature on)
- Cancel order releases reservation
- Movement log inserted on adjustment
- Multi-location feature limit (single location when off)
