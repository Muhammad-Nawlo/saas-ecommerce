# Phase 4 — Business Completion & Revenue Intelligence

## Overview

Phase 4 elevates the platform with mature promotions, double-entry ledger foundation, refund workflow, advanced reporting, analytics dashboards, and SaaS billing insights—without changing existing financial correctness or architecture.

---

## 1. Promotion Engine Maturity

### Tables (tenant)

- **promotions** — type (percentage, fixed, free_shipping, threshold, bogo), value_cents, percentage, min_cart_cents, buy_quantity/get_quantity, starts_at/ends_at, is_stackable, max_uses_total, max_uses_per_customer.
- **coupon_codes** — promotion_id, code, usage_count.
- **promotion_usages** — promotion_id, customer_id, customer_email, order_id, used_at.

### Services

- **PromotionEvaluationService** — Pure, deterministic evaluation. Input: subtotal_cents, items, candidates (with usage counts), currency. Output: applied promotions and total_discount_cents. No side effects.
- **PromotionResolverService** — Resolves candidates for a cart (tenant, coupon codes, customer id/email) and builds usage counts for evaluation.
- **RecordPromotionUsageService** — Records usage when order is paid (idempotent per order).

### Integration

- Checkout: optional `couponCodes` on `CheckoutCartCommand`. After creating order, evaluate promotions, update `OrderModel` (discount_total_cents, total_amount, applied_promotions), create payment for discounted amount.
- Payment success: snapshot `applied_promotions` passed to `OrderLockService::lock()` and stored in financial order snapshot; immutable after lock. Promotion usage recorded in `SyncFinancialOrderOnPaymentSucceededListener`.

### Invariants

- Promotion snapshot stored in order snapshot; applied promotion details immutable after lock.
- Multi-currency safe (amounts in minor units, same currency).
- Stackable vs exclusive: only one non-stackable promotion applied; stackable can combine.

---

## 2. Accounting Ledger Foundation

### Tables (tenant)

- **ledgers** — tenant_id, name, currency.
- **ledger_accounts** — ledger_id, code, name, type (revenue, tax_payable, cash, accounts_receivable, refund_liability).
- **ledger_transactions** — ledger_id, reference_type, reference_id, description, transaction_at.
- **ledger_entries** — ledger_transaction_id, ledger_account_id, type (debit/credit), amount_cents, currency, memo.

### LedgerService

- **getOrCreateLedgerForTenant(tenantId, currency)** — Creates default ledger and default accounts (REV, TAX, CASH, AR, REFUND) if missing.
- **createTransaction(ledgerId, referenceType, referenceId, description, entries)** — Creates transaction and entries in one DB transaction.
- **validateBalanced(entries)** — Throws if sum(debits) ≠ sum(credits).

### Flow

- **Payment confirmed** → `CreateLedgerTransactionOnOrderPaidListener`: Debit CASH, Credit REV (revenue), Credit TAX. reference_type = `financial_order`, reference_id = financial_order.id.
- **Refund issued** → `CreateLedgerReversalOnOrderRefundedListener`: Credit CASH, Debit REV (proportional), Debit TAX (proportional).

### Invariants

- Debit/credit always balanced; entries immutable; FinancialOrder as source reference.

---

## 3. Advanced Reporting Layer

### Services (read-only, tenant isolated)

- **RevenueReportService** — revenueToday(), revenueLastDays(days), revenueByPeriod(from, to). Cache TTL 5 min.
- **TaxReportService** — taxCollectedLastDays(days), taxByPeriod(from, to).
- **TopProductsReportService** — topProducts(limit, days) from financial_order_items (metadata.product_id).
- **ConversionReportService** — ordersToday(), conversionRateLastDays(days), averageOrderValueLastDays(days).

### API

- `GET /api/v1/reports/revenue?days=30`
- `GET /api/v1/reports/tax?days=30`
- `GET /api/v1/reports/products?limit=5&days=30`
- `GET /api/v1/reports/conversion?days=30`

Routes are tenant-scoped (InitializeTenancyBySubdomain). Reports use short TTL cache; no data mutation.

---

## 4. Merchant Analytics Dashboard (Tenant)

Filament tenant widgets (discovered in `Filament/Tenant/Widgets`):

- **RevenueTodayWidget** — Revenue today (paid orders).
- **RevenueLast30Widget** — Revenue last 30 days.
- **OrdersTodayPaidWidget** — Orders paid today.
- **ConversionRateWidget** — Conversion rate % (last 30 days).
- **AverageOrderValueWidget** — AOV last 30 days.
- **TopProductsWidget** — Top 5 products by quantity (last 30 days).
- **UsageVsPlanWidget** — Products/locations usage vs plan limits; upgrade indicator.

All use reporting services; no N+1; respect feature limits.

---

## 5. Refund & Adjustment Workflow

### Refund model (tenant)

- **refunds** — financial_order_id, amount_cents, currency, reason, status (pending/completed/failed), payment_reference, financial_transaction_id.

### RefundService

- **refund(order, amountCents, providerReference?, reason?)** — Validates refundable amount (paid - refunded), prevents over-refund, creates Refund record, creates FinancialTransaction TYPE_REFUND, updates Refund.status and financial_transaction_id, sets order status REFUNDED, dispatches OrderRefunded.
- Listeners: CreateFinancialTransactionListener (idempotent: skips if REFUND tx already exists); CreateLedgerReversalOnOrderRefundedListener (reversing ledger entries).

### Invariants

- Partial/full refund; over-refund prevented; ledger balanced after refund.

---

## 6. Subscription Billing Insight (Landlord)

### BillingAnalyticsService

- **mrr()** — Sum of active plan prices.
- **activeSubscriptionsCount()**, **activeTenantsCount()**.
- **churnRateLast30Days()** — Canceled last 30d / (active + canceled).
- **planDistribution()** — Per-plan count and revenue.

### Landlord widgets

- **MonthlyRecurringRevenueWidget** (existing).
- **ActiveSubscriptionsWidget** (existing).
- **RevenueByPlanWidget** — MRR by plan (uses BillingAnalyticsService).

---

## 7. Feature Usage Tracking

### FeatureUsageService (landlord, used in tenant context)

- **productsUsage()** — used count vs products_limit; at_limit.
- **inventoryLocationsUsage()** — count vs multi_location_inventory; at_limit when feature off and locations > 1.
- **usageSummary()** — Combined for tenant panel.

Exposed in tenant panel via **UsageVsPlanWidget** — usage vs plan limits, upgrade recommendation (at limit warning).

---

## 8. Financial Flow Diagram (Summary)

```
Checkout (with optional coupons)
  → Create Order
  → PromotionEvaluationService.evaluate()
  → Update Order (discount_total_cents, total_amount, applied_promotions)
  → Create Payment (discounted amount)
  → Payment success
  → SyncFinancialOrderOnPaymentSucceededListener
      → FinancialOrderSyncService (copy discount + applied_promotions from order)
      → OrderLockService.lock(applied_promotions) → snapshot includes applied_promotions
      → OrderPaymentService.markPaid()
      → OrderPaid
      → CreateInvoiceOnOrderPaidListener, CreateFinancialTransactionListener,
        CreateLedgerTransactionOnOrderPaidListener, RecordPromotionUsageService
```

Refund:

```
RefundService.refund(order, amount, ref?, reason?)
  → Create Refund record
  → Create FinancialTransaction TYPE_REFUND
  → OrderRefunded
  → CreateLedgerReversalOnOrderRefundedListener (reversing entries)
```

---

## 9. Cross-Module Boundaries

- **Promotions** — Evaluated at checkout (tenant); snapshot stored in operational order and financial order snapshot; usage recorded on payment success.
- **Ledger** — Created on OrderPaid and OrderRefunded; ledger lives in tenant DB; listeners run synchronously in tenant context.
- **Reports** — Read-only, tenant-scoped; cache key includes tenant_id.
- **Billing/Usage** — Landlord services read central DB (subscriptions, plans); FeatureUsageService runs in tenant context for counts (tenant DB).

---

## 10. Files Touched / Added

- **Migrations**: promotions tables, discount/applied_promotions on orders and financial_orders, ledger tables, refunds table.
- **Models**: Promotion, CouponCode, PromotionUsage; Ledger, LedgerAccount, LedgerTransaction, LedgerEntry; Refund.
- **Services**: PromotionEvaluationService, PromotionResolverService, RecordPromotionUsageService; LedgerService; RevenueReportService, TaxReportService, TopProductsReportService, ConversionReportService; BillingAnalyticsService, FeatureUsageService; RefundService (enhanced).
- **Listeners**: CreateLedgerTransactionOnOrderPaidListener, CreateLedgerReversalOnOrderRefundedListener; RecordPromotionUsage in SyncFinancialOrderOnPaymentSucceededListener.
- **API**: ReportsController, routes api/v1/reports.php.
- **Filament**: Tenant widgets (Revenue today, Revenue 30d, Orders today paid, Conversion, AOV, Top products, Usage vs plan); Landlord RevenueByPlanWidget.
- **Checkout**: CheckoutCartCommand couponCodes; CheckoutOrchestrator promotion evaluation and order update.
- **Financial**: OrderLockService lock(appliedPromotions), buildSnapshot(discount, applied_promotions); FinancialOrderSyncService discount_total_cents; RefundService creates Refund and FinancialTransaction.
