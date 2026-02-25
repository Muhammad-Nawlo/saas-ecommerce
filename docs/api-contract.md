# API Contract (v1)

This document describes the tenant-facing `/api/v1` API contract for investor and compliance reference. No code changes are implied beyond this documentation.

## Base URL and versioning

- Tenant API: `{tenant_domain}/api/v1`
- All monetary amounts are in **minor units** (cents) unless otherwise stated.
- Dates/timestamps: ISO 8601 where applicable.

## Authentication

- **Customer auth**: `Authorization: Bearer {token}` (Sanctum, guard `customer`) for customer endpoints (profile, addresses, orders as customer).
- **Dashboard/manager auth**: `Authorization: Bearer {token}` (Sanctum) for catalog, orders, payments, checkout, cart, inventory, reports.
- Endpoints that require auth are documented as such below. Unauthenticated requests to protected routes return `401`.

## Common response structure

- **Success**: JSON body with resource or `data` key; HTTP 2xx.
- **Validation error**: `422`, body `message` and `errors` (field-keyed array).
- **Not found**: `404`, optional `message`.
- **Forbidden**: `403` when tenant/subscription or permission checks fail.

## Error codes (typical)

| HTTP | Meaning |
|------|--------|
| 400 | Bad request (e.g. invalid payload) |
| 401 | Unauthenticated |
| 403 | Forbidden (permission/tenant) |
| 404 | Resource not found |
| 422 | Validation failed |
| 503 | System read-only or maintenance |

---

## Endpoints (summary)

### Catalog (`/api/v1/...`)

- `GET /products` – List products (paginated). Optional auth.
- `GET /products/{id}` – Product detail.
- `POST /products`, `PATCH /products/{id}/price`, `POST /products/{id}/activate|deactivate` – Auth required.

### Checkout

- `POST /checkout` – Create checkout session. Auth.
- `POST /checkout/confirm-payment` – Confirm payment. Auth; throttle: payment-confirm.

### Orders

- `POST /orders` – Create order. Auth.
- `GET /orders/{orderId}` – Order detail.
- `POST /orders/{orderId}/items` – Add item.
- `POST /orders/{orderId}/confirm` – Confirm order.
- `POST /orders/{orderId}/pay` – Initiate payment.
- `POST /orders/{orderId}/ship` – Mark shipped.
- `POST /orders/{orderId}/cancel` – Cancel order.

### Payments

- `POST /payments` – Create payment. Auth.
- `GET /payments/order/{orderId}` – List payments for order.
- `POST /payments/{paymentId}/confirm` – Confirm payment. Throttle: payment-confirm.
- `POST /payments/{paymentId}/refund` – Refund.
- `POST /payments/{paymentId}/cancel` – Cancel.

### Cart

- `POST /cart` – Create cart.
- `GET /cart/{cartId}` – Get cart.
- `POST /cart/{cartId}/items` – Add item.
- `PUT /cart/{cartId}/items/{productId}` – Update item.
- `DELETE /cart/{cartId}/items/{productId}` – Remove item.
- `POST /cart/{cartId}/clear` – Clear cart.
- `POST /cart/{cartId}/convert` – Convert to order.
- `POST /cart/{cartId}/abandon` – Abandon cart.

### Inventory

- `POST /inventory` – Create stock record.
- `GET /inventory/{productId}` – Stock for product.
- `POST /inventory/{productId}/increase|decrease|reserve|release` – Adjustments.
- `PATCH /inventory/{productId}/threshold` – Set low-stock threshold.

### Reports

- `GET /reports/revenue` – Revenue report.
- `GET /reports/tax` – Tax report.
- `GET /reports/products` – Product report.
- `GET /reports/conversion` – Conversion report.

### Customer (account)

- `POST /customer/register`, `POST /customer/login`, `POST /customer/forgot-password`, `POST /customer/reset-password` – Auth flow.
- `POST /customer/logout`, `GET /customer/me`, `PATCH /customer/profile` – Auth:customer.
- Addresses: `GET|POST|PATCH|DELETE /customer/addresses` (and `/{id}`).
- `POST /customer/password/change`, `GET /customer/export`, `DELETE /customer/account` – Auth:customer.

---

## Financial snapshot fields (audit)

For tamper detection and compliance, the following entities store an immutable snapshot and a SHA-256 `snapshot_hash` at lock/issue/confirm time:

- **Financial order**: Totals, currency, snapshot (items, tax lines, promotions), `locked_at`. Hash set at lock.
- **Invoice**: Totals, currency, snapshot, `issued_at` / `locked_at`. Hash set at issue.
- **Payment**: Amount, currency, status, payment_currency, payment_amount, exchange_rate_snapshot (when used). Hash set when status becomes `succeeded`.

Any response that exposes these entities may include `snapshot_hash` for verification; clients must not modify financial fields after lock/issue/confirm.

---

## Money representation

- All monetary values in the API are in **minor units** (e.g. cents).
- Currency is a three-letter code (e.g. `USD`, `EUR`).
- Example: `"total_cents": 1999` with `"currency": "USD"` = $19.99.

---

## Landlord / central API

- Billing and subscription endpoints live under the landlord/central app (e.g. `/api/landlord/billing`, plans, subscriptions, checkout, webhook). They are outside the tenant `/api/v1` contract and use central auth/tenant context as configured.
