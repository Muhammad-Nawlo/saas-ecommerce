# Financial Modeling System

Production-grade, immutable, auditable, tax-safe, currency-safe, snapshot-based financial modeling for the SaaS property management platform.

## Table Names

To avoid clashing with the existing e-commerce `orders` table, financial tables use a prefix:

- `financial_orders` — order header (snapshot, locked_at, totals in cents)
- `financial_order_items` — line items
- `tax_rates` — tax rate definitions (snapshotted into order at lock)
- `financial_order_tax_lines` — snapshot of tax applied at lock
- `financial_transactions` — debit/credit/refund ledger

Use `FinancialOrder`, `FinancialOrderItem`, `TaxRate`, `FinancialOrderTaxLine`, `FinancialTransaction` models.

## Money Architecture

- **Never store money as float/decimal.** All amounts are **BIGINT in cents**.
- Currency is stored explicitly (ISO 4217, 3-letter).
- **Value object:** `App\ValueObjects\Money` — immutable, `add()`, `subtract()`, `multiply()`, `equals()`, `format()`. Throws on currency mismatch.

## Order Lifecycle

1. **Draft** — Create order and items; edit freely.
2. **Lock** — Call `OrderLockService::lock($order, $countryCode?, $regionCode?)` to:
   - Compute subtotal, tax (via `TaxCalculator`), total
   - Snapshot items and tax lines into `order_tax_lines` and `order.snapshot` JSON
   - Set `locked_at` and status `pending`
   - Dispatch `OrderLocked`
3. **Paid** — Call `OrderPaymentService::markPaid($order, $providerReference)` to set `paid_at`, status `paid`, and dispatch `OrderPaid` (listener creates credit transaction).
4. **Refund** — Call `RefundService::refund($order, $amountCents, $providerReference?)`; validates refund ≤ paid amount, creates refund transaction via listener, sets status `refunded`, dispatches `OrderRefunded`.

After lock, the order is **immutable** (no item/tax edits). Tax is read from snapshot only.

## Services

- `App\Services\Financial\TaxCalculator` — `calculate(FinancialOrder $order, iterable $applicableRates): TaxResult` (subtotal_cents, tax_total_cents, total_cents, tax_lines, currency).
- `App\Services\Financial\OrderLockService` — Locks draft order, snapshots, dispatches `OrderLocked`.
- `App\Services\Financial\OrderPaymentService` — Marks order paid, dispatches `OrderPaid`.
- `App\Services\Financial\RefundService` — Refund with overpayment check; dispatches `OrderRefunded`.

## Events & Listeners

- **OrderLocked** — Audit log (tenant).
- **OrderPaid** — Create credit `FinancialTransaction`; audit log.
- **OrderRefunded** — Create refund `FinancialTransaction`; audit log.

Subscribers: `CreateFinancialTransactionListener`, `AuditLogOrderStatusListener` (in `EventServiceProvider`).

## Filament (Tenant Panel)

Under **Financial** group:

- **Financial Order** — List/create/edit/view. Subtotal, tax, total, status, locked_at. Edit disabled when locked. View shows snapshot breakdown.
- **Tax Rate** — CRUD (name, percentage, country_code, region_code, is_active).
- **Financial Transaction** — List only (type, amount, currency, status, order, provider_reference).

All scoped by tenant.

## Integrity Rules

- `total_cents = subtotal_cents + tax_total_cents` (enforced at lock).
- Transactions cannot exceed order total (enforced by business logic).
- Refund cannot exceed paid amount (enforced in `RefundService`).
- Currency mismatch throws in `Money` and in payment/refund flows.
- All writes use `DB::transaction()` where appropriate.

## Running Tests

Financial and Money tests:

```bash
php artisan test tests/Unit/ValueObjects/MoneyTest.php
php artisan test tests/Feature/Financial/
```

**Note:** If the app fails to boot (e.g. Filament 3 type compatibility with existing Landlord resources), fix those first or run Money unit tests in isolation. Financial feature tests require tenant DB migrations (including `database/migrations/tenant/*financial*` and `*tax_rates*`).

## Migrations

Tenant migrations (run per tenant):

- `2026_02_25_100000_create_financial_orders_table.php`
- `2026_02_25_100001_create_financial_order_items_table.php`
- `2026_02_25_100002_create_tax_rates_table.php`
- `2026_02_25_100003_create_financial_order_tax_lines_table.php`
- `2026_02_25_100004_create_financial_transactions_table.php`

Indexes on `order_number`, `status`, `tenant_id`, and FKs are in place.
