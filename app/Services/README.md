# Services (Application Services)

## Purpose

Application-level services that span or sit beside modules: Currency (conversion, tenant settings), Customer (guest order linking, promotion eligibility), Invoice (create from order, issue, apply payment, credit note), Financial (order lock, sync, ledger, tax, refund), Inventory (allocation, transfer), Promotion (resolve, evaluate, record usage), Reporting (revenue, tax, products, conversion). Used by Filament tenant, API, and event listeners.

## Main Services by Area

- **Currency** — CurrencyService, CurrencyConversionService; exchange rates, tenant base/display currency; used when locking financial orders (OrderCurrencySnapshotService) and displaying amounts.
- **Customer** — LinkGuestOrdersToCustomerService, CustomerPromotionEligibilityService.
- **Invoice** — InvoiceService (createFromOrder, issue, applyPayment, createCreditNote, void), InvoiceNumberGenerator, InvoicePdfGenerator. **Writes financial-related data** (invoices, invoice_items, invoice_payments, credit_notes); uses Money; assumes tenant context.
- **Financial** — OrderLockService (lock FinancialOrder: tax, snapshot, hash; dispatches OrderLocked), FinancialOrderSyncService (sync from operational order), PaymentSnapshotService, OrderPaymentService, TaxCalculator, RefundService. **Writes financial_orders, financial_transactions, ledger.** OrderLockService must run in transaction; immutability after lock.
- **Inventory** — InventoryAllocationService (allocate/release for multi-location), InventoryTransferService.
- **Promotion** — PromotionResolverService, PromotionEvaluationService, RecordPromotionUsageService; used by Checkout.
- **Reporting** — RevenueReportService, TaxReportService, TopProductsReportService, ConversionReportService; read-only reporting.

## Event Flow

- Listeners in app/Listeners (Financial, Invoice) call these services (e.g. CreateInvoiceOnOrderPaidListener → InvoiceService::createFromOrder; CreateLedgerTransactionOnOrderPaidListener; SyncFinancialOrderOnPaymentSucceededListener → FinancialOrderSyncService).

## External Dependencies

- **Modules** — Orders, Payments, Shared (Money, exceptions), Financial models.
- **Landlord** — FeatureResolver/tenant_feature used by some flows (e.g. multi-location).

## Interaction With Other Modules

- **Checkout** — Uses Promotion*, InventoryAllocationService.
- **Orders/Payments** — Events trigger Invoice and Financial services.
- **Filament Tenant** — Resources and pages use these services for CRUD and reporting.

## Tenant Context

- **All services assume tenant context** when operating on tenant data (invoices, financial orders, etc.). ReconcileFinancialDataJob initializes tenancy per tenant before calling reconciliation.

## Financial Data

- **Invoice and Financial services write financial data.** InvoiceService: invoices, payments, credit notes. OrderLockService: financial_orders (totals, snapshot, hash). FinancialOrderSyncService, CreateFinancialTransactionListener, CreateLedger* listeners: financial_orders, financial_transactions, ledger_entries. All amounts in **minor units (cents)**; float forbidden. Snapshot and hash used for immutability and tamper detection.
