# Financial Architecture (Audit & Compliance)

This document describes the financial flow, lock process, snapshot logic, and immutability rules for investor and compliance reference.

---

## 1. Operational Order vs Financial Order

- **Operational Order** (`orders`): Cart/checkout and fulfillment lifecycle. Mutable; holds items, status (draft → confirmed → paid → shipped, etc.), customer, promotions. Used for inventory, shipping, and UX.
- **Financial Order** (`financial_orders`): Created from the operational order when payment or locking is needed. Holds **immutable** financial totals (subtotal, tax, discount, total), currency, and a **snapshot** of items and tax lines at lock time. Used for invoicing, ledger, and reporting.

Flow: Operational order is confirmed → when payment is initiated or order is locked, a **Financial Order** is synced (or created) from the operational order. The financial order is then **locked**; after that, financial fields and snapshot are immutable.

---

## 2. Lock Process

1. **When**: Before payment is recorded or when the order is finalized for accounting (e.g. transition draft → pending).
2. **Who**: `OrderLockService::lock(FinancialOrder $order, ...)`.
3. **Steps**:
   - Validate: order is draft, has items.
   - Compute tax (via `TaxCalculator`) and applicable rates.
   - In a DB transaction:
     - Set `subtotal_cents`, `tax_total_cents`, `total_cents`, `locked_at`, `status = pending`.
     - Persist item-level totals and tax lines.
     - Build **snapshot** (items, tax lines, promotions, totals) via `buildSnapshot()`.
     - Fill currency snapshot (base/display) if multi-currency is used.
     - Compute **SHA-256 snapshot hash** from immutable fields and set `snapshot_hash`.
     - Save the financial order.
   - Dispatch `OrderLocked` event (audit log, ledger readiness, etc.).

4. **Immutability**: After lock, any update to locked attributes (totals, currency, snapshot, etc.) throws `FinancialOrderLockedException` and is logged to the **security** channel. No DB change is persisted.

---

## 3. Snapshot Logic

- **Snapshot** is a JSON structure stored on the financial order (and copied to the invoice when created). It includes:
  - `locked_at`, `currency`, `subtotal_cents`, `discount_total_cents`, `tax_total_cents`, `total_cents`
  - `items` (line-level description, quantity, unit_price_cents, subtotal_cents, tax_cents, total_cents)
  - `tax_lines`, `applied_promotions`
- **Snapshot hash**: At lock time, a deterministic serialization of the immutable fields (including snapshot) is hashed with SHA-256 and stored in `snapshot_hash`. Used for **tamper detection** only: `verifySnapshotIntegrity()` recomputes the hash and compares; on mismatch, a critical security log is written. No auto-correction.

---

## 4. Payment Event Pipeline

1. **Create payment**: Payment is created (e.g. via Payments API or checkout) with status `pending`.
2. **Confirm payment**: Provider (e.g. Stripe) confirms; `ConfirmPaymentHandler` marks payment as **succeeded**.
3. **Persistence**: When the payment is saved with status `succeeded`, the **Payment** model sets `snapshot_hash` on first save (hash of immutable payment fields: amount, currency, status, etc.).
4. **Downstream**: `PaymentSucceeded` domain event is dispatched. Listeners:
   - **SyncFinancialOrderOnPaymentSucceededListener**: Ensures financial order exists, locks it if needed, marks it **paid**, fills payment snapshot; dispatches **OrderPaid** (financial).
   - **CreateFinancialTransactionListener** (on OrderPaid): Creates financial transaction record (credit) for the order.
   - **CreateLedgerTransactionOnOrderPaidListener**: Creates balanced ledger transaction (debit/credit entries) for revenue, tax, cash.
   - **CreateInvoiceOnOrderPaidListener**: Can create/issue invoice from the locked financial order.

5. **Immutability**: Once payment status is `succeeded`, amount, currency, and related fields cannot be changed; attempts throw `PaymentConfirmedException` and are logged to the security channel.

---

## 5. Invoice Generation

- **Source**: Locked **Financial Order** (with snapshot).
- **Create**: `InvoiceService::createFromOrder(FinancialOrder)` creates a **draft** invoice from the order’s snapshot (totals, items). No recalculation after creation.
- **Issue**: `InvoiceService::issue(Invoice)` sets `status = issued`, `issued_at`, `locked_at`, merges snapshot with issued totals, computes **snapshot_hash**, and saves. After issue, totals and snapshot are immutable; updates throw `InvoiceLockedException` and are logged to the security channel.
- **Payments / credit notes**: Applied against the issued invoice; balance and status (e.g. paid, partially_paid, refunded) updated without changing the locked totals.

---

## 6. Ledger Balancing

- **Ledger**: One per tenant; holds **accounts** (e.g. Revenue, Tax payable, Cash, Refund liability) and **transactions**.
- **Ledger transaction**: Immutable; has `reference_type` and `reference_id` (e.g. `financial_order`, `refund`). Each transaction has **entries** (debit/credit) per account.
- **Rule**: For every ledger transaction, sum of debits = sum of credits (balanced). Integrity check command verifies this for all transactions referencing financial orders and refunds.

---

## 7. Refund Reversal Process

1. **Refund request**: `RefundService::refund()` validates refundable amount (paid − already refunded), creates a **Refund** record and a **financial transaction** (type REFUND).
2. **Order status**: Financial order status set to `refunded`; `OrderRefunded` event dispatched.
3. **Ledger**: `CreateLedgerReversalOnOrderRefundedListener` creates a **reversing** ledger transaction (debits/credits opposite to the original payment) so the ledger stays balanced.
4. **Audit**: Refund is logged with structured audit (event_type `order_refunded`, before/after state).

---

## 8. Immutability Rules (Summary)

| Entity            | Condition              | Immutable fields                                                                 | Exception / log           |
|-------------------|------------------------|-----------------------------------------------------------------------------------|---------------------------|
| Financial Order   | `status != draft`      | Totals, currency, snapshot, base/display amounts                                | FinancialOrderLockedException; security log |
| Invoice           | `status = issued` (etc.) | Totals, currency, snapshot                                                     | InvoiceLockedException; security log |
| Payment           | `status = succeeded`   | amount, currency, payment_currency, payment_amount, exchange_rate_snapshot     | PaymentConfirmedException; security log |

- **No silent plan changes**: Subscription plan and status changes are audited with explicit event types (`subscription_plan_changed`, `subscription_cancelled`, `subscription_renewed`, `subscription_payment_failure`) in the landlord audit log.

---

## 9. Integrity Verification

- **Command**: `php artisan system:integrity-check [--tenant=id]`
- **Checks**:
  1. **Snapshot hashes**: For each locked financial order, issued invoice, and succeeded payment that has a `snapshot_hash`, recompute hash and compare; on mismatch, report and log to security.
  2. **Ledger balance**: For each ledger transaction referencing financial orders or refunds, ensure sum(debits) = sum(credits).
  3. **Invoice vs financial order**: For each invoice linked to a financial order, ensure invoice total equals financial order total.
- **Output**: `PASS` or a table of mismatches (no auto-correction).

---

## 10. Security and Audit

- **Security log channel**: Dedicated `security` log channel (e.g. `storage/logs/security.log`) for: snapshot hash mismatch, immutability violation attempts, cross-tenant access attempts, payment double-processing attempts.
- **Structured audit**: Key events (order locked, order paid, order refunded, invoice issued, payment confirmed, subscription plan change/cancel/renewal/payment failure) are logged with: `tenant_id`, `actor_id`, `entity_type`, `entity_id`, `event_type`, `before_state`, `after_state`, `timestamp` (in properties or top-level as applicable).
