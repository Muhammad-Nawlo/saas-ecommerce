# Financial Module (Application)

## Purpose

Contains the **FinancialReconciliationService** only. This module does not own the FinancialOrder/FinancialTransaction/Ledger models (those live in app/Models/Financial and app/Models/Ledger). It provides reconciliation logic: detect mismatches between financial_orders, invoices, ledger (debits vs credits), and payment sums. Does **not** auto-fix; only logs and returns issues.

## Main Models / Entities

- None in this module. Uses: FinancialOrder, FinancialTransaction, FinancialOrderItem, FinancialOrderTaxLine, LedgerEntry, LedgerTransaction, Invoice from app/Models.

## Main Services

- **FinancialReconciliationService** — `reconcile(?string $tenantId)`: runs checks for current tenant (or given tenant ID). `verify()` throws if any issues. Checks: ledger balanced (debits = credits), invoice total vs financial order total, payments sum vs order total for paid orders. **Assumes tenant context** when called from ReconcileFinancialDataJob (tenancy initialized per tenant).

## Event Flow

- This module does not dispatch events. It is invoked by **ReconcileFinancialDataJob** (scheduler), which initializes tenancy for each tenant and calls `reconcile()`.

## External Dependencies

- **Models** — FinancialOrder, FinancialTransaction, Invoice, LedgerEntry, LedgerTransaction.
- **Tenancy** — Caller (e.g. ReconcileFinancialDataJob) must initialize tenant context for each tenant.

## Interaction With Other Modules

- **Orders / Payments** — Reconciliation reads data written by Financial listeners (CreateFinancialTransactionListener, CreateLedgerTransactionOnOrderPaidListener, SyncFinancialOrderOnPaymentSucceededListener, CreateInvoiceOnOrderPaidListener).
- **Jobs** — ReconcileFinancialDataJob loops tenants and calls this service.

## Tenant Context

- **Requires tenant context** when run per tenant. ReconcileFinancialDataJob sets tenant via `tenancy()->initialize($tenant)` before calling `reconcile()`.

## Financial Data

- **Read-only.** Only reads financial_orders, financial_transactions, invoices, ledger_* to detect inconsistencies. No writes; no float math; amounts in cents.
