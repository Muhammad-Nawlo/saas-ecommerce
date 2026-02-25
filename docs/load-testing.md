# Load Test Preparation

This document describes recommended load test scenarios and areas to validate for race conditions. Use with k6, Artillery, or your preferred tool.

---

## 1. Scenarios

### 1.1 Checkout concurrency

- **Scenario**: 100 concurrent checkouts (create cart → add items → convert to order or initiate payment).
- **Goals**: No deadlocks; consistent inventory reservation; no duplicate orders for same session/cart.
- **Endpoints**: `POST /api/v1/cart`, `POST /api/v1/cart/{id}/items`, `POST /api/v1/cart/{id}/convert`, or full checkout flow.
- **Validate**: Response times, error rate, and that inventory and order counts remain consistent (no oversell).

### 1.2 Payment confirmation

- **Scenario**: 50 concurrent payment confirmations (different orders/payments).
- **Goals**: Each payment is applied exactly once; financial order and ledger stay consistent; idempotency keys prevent double-processing.
- **Validate**: Run same payment confirm twice (e.g. duplicate webhook) and assert only one financial order update and one ledger transaction.

### 1.3 Product reads

- **Scenario**: 1000 concurrent product reads (catalog listing and detail).
- **Goals**: Read replica (if used) handles load; no N+1; cache helps where applicable.
- **Endpoints**: `GET /api/v1/products`, `GET /api/v1/products/{id}`.

### 1.4 Multi-tenant mixed load

- **Scenario**: Mix of tenants: 10 tenants × (20 checkouts + 50 product reads + 5 payment confirmations) over 60s.
- **Goals**: No cross-tenant data leak; cache keys isolated per tenant; DB connection/tenant context correct.
- **Validate**: Spot-check responses and DB to ensure tenant_id isolation.

---

## 2. Race Conditions to Check

### 2.1 Inventory allocation

- **Risk**: Two requests allocate the same stock (oversell).
- **Mitigation**: Allocation and reservation in transactions; unique constraints (e.g. product_id + location_id); pessimistic or optimistic locking where needed.
- **Test**: High concurrency on “decrease stock” or “reserve” for the same product/location; assert total allocated ≤ available.

### 2.2 Payment confirmation

- **Risk**: Duplicate webhook or retry causes double mark-paid or double ledger entry.
- **Mitigation**: Idempotency key `payment_confirmed:{paymentId}`; listener skips if already processed.
- **Test**: Send same PaymentSucceeded (or confirm request) twice; assert single financial order status update and single ledger transaction.

### 2.3 Order locking

- **Risk**: Order locked twice or totals changed after lock.
- **Mitigation**: Status check (draft only) and transaction in `OrderLockService`; immutability guards on FinancialOrder.
- **Test**: Concurrent lock attempts for same order; only one lock succeeds; after lock, mutation attempts throw and are logged.

---

## 3. Tooling Suggestions

- **k6**: Script scenarios with `tenant_id` and auth headers; use thresholds for p95 latency and error rate.
- **Artillery**: YAML scenarios for API flows; plug in tenant and product IDs from a CSV.
- **Laravel**: Use `RefreshDatabase` and factories in feature tests to simulate concurrency (e.g. parallel HTTP requests) and assert invariants (counts, idempotency).

---

## 4. Success Criteria

- No 5xx under target load (e.g. 100 checkouts, 50 payments).
- p95 latency within SLA (e.g. &lt; 2s for checkout, &lt; 500ms for reads).
- No duplicate financial records (payment/refund idempotency).
- No oversell (inventory allocation under concurrency).
- No cross-tenant data in responses or DB.
