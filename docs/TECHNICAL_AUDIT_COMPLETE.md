# Complete Technical Audit — Multi-Tenant SaaS E-Commerce Platform

**Audit date:** 2026-02-24 (full system audit)  
**Scope:** Entire codebase — structured system audit. No code changes.  
**Assumption:** Project targets serious SaaS scale and significant GMV.

---

## SECTION 1 — HIGH LEVEL SYSTEM MAP

### 1.1 Identification of Module Groups

**Core modules (ecommerce, tenant DB):**
- **Catalog** — Products (CRUD, activate/deactivate); product limit enforced.
- **Cart** — Cart + items, convert to order; total in minor units.
- **Checkout** — Orchestrator: cart → order (OrderModel) → promotion evaluation → payment intent; inventory reserve/allocate.
- **Orders** — OrderModel, order_items; create, add item, confirm, pay, ship, cancel; domain Order entity + Eloquent repository.
- **Payments** — Create payment intent, confirm payment; Payment entity; Stripe gateway; domain event PaymentSucceeded on markSucceeded.
- **Inventory** — Stock (single-location: stock_items; multi-location: inventory_locations, inventory_location_stocks, reservations, movements, transfers); reserve/release/allocate.

**Shared module (cross-cutting):**
- **Shared** — Value objects (Money, TenantId, etc.), domain exceptions (CurrencyMismatchException, FeatureNotEnabledException, PaymentAlreadyProcessedException, FinancialOrderLockedException), messaging (EventBus, LaravelEventBus), TransactionManager, Audit (LogAuditEntry job, observers). Used by all tenant modules and some app-level services.

**Financial modules (app-level, tenant DB):**
- **Financial** — FinancialOrder, FinancialOrderItem, FinancialOrderTaxLine, FinancialTransaction; OrderLockService, OrderPaymentService, FinancialOrderSyncService, TaxCalculator; OrderLocked / OrderPaid / OrderRefunded events.
- **Invoice** — Invoice, invoice_items, invoice_payments, credit_notes; InvoiceService, InvoiceNumberGenerator; create from FinancialOrder, issue, applyPayment, credit note.
- **Ledger** — ledgers, ledger_accounts, ledger_transactions, ledger_entries; LedgerService (createTransaction, validateBalanced, getOrCreateLedgerForTenant); listeners create ledger tx on OrderPaid and reversing tx on OrderRefunded.
- **Currency (tenant)** — CurrencyService, ExchangeRateService, CurrencyConversionService, OrderCurrencySnapshotService, PaymentSnapshotService; currencies, exchange_rates, tenant_currency_settings; multi-currency feature-gated.
- **Refund** — Refund model (refunds table); RefundService (validate refundable, create Refund + FinancialTransaction, dispatch OrderRefunded).
- **Promotion** — Promotion, CouponCode, PromotionUsage; PromotionEvaluationService (pure), PromotionResolverService, RecordPromotionUsageService; applied at checkout; snapshot in order and financial order.

**Billing (landlord) modules:**
- **Landlord** — Tenants, domains, plans, features, plan_features, subscriptions; Stripe (checkout, webhook, portal); FeatureResolver, BillingAnalyticsService, FeatureUsageService; StripeWebhookController, BillingCheckoutController; SubscriptionCancelled and listeners.

**Tenant modules (by responsibility):**
- Same as Core + Financial above; all run in tenant context (tenant DB) except when FeatureResolver/BillingAnalyticsService read central DB.

**Infrastructure modules:**
- **Persistence** — Eloquent repositories (Order, Payment, Cart, Product, StockItem); OrderModel, PaymentModel, etc.; all use EventBus to publish domain events on save.
- **HTTP** — API v1 routes (catalog, cart, checkout, orders, payments, inventory, customer, reports); FormRequests; JsonResource/Resource responses; tenant by subdomain (InitializeTenancyBySubdomain).
- **Filament** — Tenant panel (/dashboard): resources for Products, Orders, Invoices, Financial Orders, Customers, Inventory, Currencies, Promotions, etc.; Landlord panel (/admin): Tenants, Plans, Subscriptions, Audit.

---

### 1.2 Architecture Style and Boundaries

**Overall style:** **Modular monolith with DDD-ish layering and a strong service layer.**

- **Modular monolith:** Single deployable app; clear module boundaries (Catalog, Cart, Checkout, Orders, Payments, Inventory in `app/Modules/*`). Financial/Invoice/Ledger/Currency/Refund/Promotion live in `app/Services`, `app/Models`, `app/Listeners` — not under Modules, but with clear responsibilities.
- **DDD-ish:** Application (Commands, Handlers, DTOs, Contracts), Domain (Entities, ValueObjects, Events, Exceptions), Infrastructure (Persistence, Gateways) within each module. Repositories and EventBus abstract persistence and messaging.
- **Service layer:** CheckoutOrchestrator, OrderLockService, OrderPaymentService, FinancialOrderSyncService, InvoiceService, LedgerService, CurrencyConversionService, PromotionEvaluationService, RefundService, etc. encapsulate transactions and business rules.

**Domain boundaries:**
- **Sales boundary:** Cart → Checkout → Order (OrderModel) + Payment (Payments module). Single source of truth for “operational” order and payment status.
- **Financial boundary:** FinancialOrder (synced from OrderModel on payment success), lock (tax + currency snapshot), mark paid → OrderPaid → Invoice, FinancialTransaction, Ledger transaction. Single source of truth for tax, invoice, and ledger.
- **Bridge:** PaymentSucceeded (from Payments module, when payment is saved after markSucceeded) → SyncFinancialOrderOnPaymentSucceededListener → sync FinancialOrder, lock, markPaid → OrderPaid → invoice + financial_transaction + ledger. No duplicate order aggregate; operational order drives financial sync once.

**Event-driven parts:**
- **Domain events (aggregate → EventBus):** Payment entity records PaymentSucceeded on markSucceeded(); repository save() publishes via EventBus (LaravelEventBus dispatches to Laravel Dispatcher). Order entity records OrderPaid (Orders module) when markAsPaid(); that is separate from Financial\OrderPaid.
- **Laravel event listeners:** PaymentSucceeded → SyncFinancialOrderOnPaymentSucceededListener (sync/lock/markPaid), OrderPaidListener (log, queued), SendOrderConfirmationEmailListener (queued). OrderPaid (Financial) → CreateInvoiceOnOrderPaidListener, CreateFinancialTransactionListener, CreateLedgerTransactionOnOrderPaidListener. OrderRefunded → CreateLedgerReversalOnOrderRefundedListener. CreateFinancialTransactionListener and AuditLogOrderStatusListener subscribe to OrderLocked/OrderPaid/OrderRefunded.
- **Observers:** Product, Order, StockItem, User, Plan, Feature, Subscription, Tenant trigger audit (LogAuditEntry job or direct write).

**Cross-module dependencies:**
- Checkout → Cart, Orders (OrderService), Payments (PaymentService), Inventory (InventoryService, InventoryAllocationService), Promotion (PromotionResolverService, PromotionEvaluationService). Checkout does not depend on Financial or Invoice directly; the link is PaymentSucceeded → SyncFinancialOrderOnPaymentSucceededListener.
- FinancialOrderSyncService depends on OrderModel (Modules\Orders\Infrastructure). OrderLockService depends on TaxCalculator, OrderCurrencySnapshotService. LedgerService and CreateLedgerTransactionOnOrderPaidListener depend on FinancialOrder and Ledger models.
- FeatureResolver (Landlord) is used in tenant context (CurrencyService, InventoryLocationService, CreateProductHandler, Filament) — cross-boundary read-only dependency.

---

### 1.3 Logical Flows (Text)

**Checkout flow:**
1. Client POST checkout (cartId, paymentProvider, customerEmail, customerId?, couponCodes?).
2. CheckoutOrchestrator: getActiveCart → validateStock → (optional) reserveStock (single-location) or later allocateStock (multi-location).
3. In transaction: createOrderFromCart (OrderModel + order_items) → (if multi-location) allocateStock(order).
4. Promotion: getCandidates(tenant, couponCodes, customerId, email) → evaluate(subtotal, items, candidates, currency) → discount_total_cents, applied_promotions.
5. Update OrderModel: discount_total_cents, total_amount = subtotal - discount, applied_promotions.
6. createPayment(orderId, discounted amount, provider) → CreatePaymentHandler → Payment entity + gateway createPaymentIntent → return payment_id, client_secret.
7. markCartConverted(cartId, orderId). Response: orderId, paymentId, clientSecret, amount, currency.

**Payment flow (confirm):**
1. Client POST confirm-payment (paymentId, providerPaymentId).
2. ConfirmPaymentHandler: find Payment → if status succeeded throw PaymentAlreadyProcessedException → confirmPayment (gateway) → payment.markSucceeded() → orderApplicationService.markOrderAsPaid(orderId) → paymentRepository.save(payment).
3. On save, EloquentPaymentRepository publishes payment.pullDomainEvents() via EventBus → PaymentSucceeded dispatched to Laravel.
4. SyncFinancialOrderOnPaymentSucceededListener (sync): load OrderModel → FinancialOrderSyncService.syncFromOperationalOrder (create or reuse FinancialOrder, copy items, discount, totals) → if not locked: OrderLockService.lock(financialOrder, null, null, order.applied_promotions) → OrderPaymentService.markPaid(financialOrder, providerReference) → PaymentSnapshotService.fillSnapshot(payment) → RecordPromotionUsageService.recordForOrder(...).
5. OrderPaymentService.markPaid: status=paid, paid_at=now, event(OrderPaid(financialOrder, providerReference)).
6. OrderPaid listeners: CreateInvoiceOnOrderPaidListener (idempotent), CreateFinancialTransactionListener (idempotent), CreateLedgerTransactionOnOrderPaidListener.

**Financial flow:**
1. Operational order paid (above) → SyncFinancialOrderOnPaymentSucceededListener creates/gets FinancialOrder, locks it (tax + currency snapshot + applied_promotions in snapshot), marks paid.
2. Lock: TaxCalculator on FinancialOrder items → tax lines + subtotal/tax_total/total; discount_total_cents applied; buildSnapshot (items, tax_lines, applied_promotions); OrderCurrencySnapshotService.fillSnapshot (base/display amounts if multi-currency); status → pending, locked_at set.
3. Mark paid: status → paid, paid_at set; OrderPaid dispatched.
4. FinancialTransaction (TYPE_CREDIT, amount=order.total_cents) created by CreateFinancialTransactionListener (idempotent).
5. Ledger: CreateLedgerTransactionOnOrderPaidListener creates balanced transaction (Debit CASH, Credit REV, Credit TAX) with reference_type=financial_order, reference_id=financial_order.id.

**Invoice flow:**
1. CreateInvoiceOnOrderPaidListener handles OrderPaid: if config auto_generate_invoice_on_payment and no invoice for order_id → InvoiceService.createFromOrder(financialOrder).
2. createFromOrder: copy from order snapshot, create invoice_items, generate invoice_number, status=draft, snapshot stored.
3. Issue (manual or workflow): InvoiceService.issue(invoice) → status=issued, locked_at set; immutability guard prevents later changes to totals/snapshot.
4. applyPayment: invoice_payments record, update status (partially_paid/paid), paid_at when fully paid.
5. Credit notes: createCreditNote; balance reduced; snapshot preserved.

**Ledger flow:**
1. On OrderPaid: CreateLedgerTransactionOnOrderPaidListener gets/creates ledger for tenant, ensures default accounts (CASH, REV, TAX); creates LedgerTransaction with entries: Debit CASH total_cents, Credit REV (subtotal - discount), Credit TAX tax_total_cents; reference_type=financial_order, reference_id=order.id.
2. On OrderRefunded: CreateLedgerReversalOnOrderRefundedListener creates reversing LedgerTransaction: Credit CASH refund amount, Debit REV (proportional), Debit TAX (proportional); reference_type=refund.
3. LedgerService.createTransaction enforces validateBalanced (debits = credits); entries immutable (no update API).

**Wallet flow:**
- **Not implemented.** No vendor/seller entity, no wallet, no commission or payout workflow. LedgerAccount::TYPE_PLATFORM_COMMISSION exists as a placeholder only.

---

## SECTION 2 — FOLDER STRUCTURE ANALYSIS

**app/**
- **Console/Commands:** RetentionPruneCommand (audit, inventory_movements, stripe_events). Responsibility: scheduled cleanup. Clean.
- **Constants/, Enums/:** LandlordPermissions, TenantPermissions, LandlordRole, TenantRole. Centralized RBAC. Clean.
- **Events:** JobFailed (queue failure). Financial events (OrderPaid, OrderLocked, OrderRefunded) in Events/Financial. Clear.
- **Filament/**  
  - **Landlord:** Resources (Tenant, Plan, Feature, Subscription, AuditLog, etc.), Widgets (MRR, ActiveSubscriptions, RevenueByPlan, etc.). Panel /admin.  
  - **Tenant:** Resources (Product, Order, Invoice, FinancialOrder, Customer, Inventory, Currency, Promotion, etc.), Widgets (Revenue today/30d, Orders today, Conversion, AOV, Top products, Usage vs plan), Pages (Billing, DomainSettings). Panel /dashboard.  
  Responsibility: admin UI. Smell: some Landlord resources have Filament type issues ($navigationGroup) that break test bootstrap.
- **Helpers:** tenant_features.php (tenant_feature, tenant_limit). Global helpers; used across tenant code. Acceptable.
- **Http/Controllers:** HealthController, InvoicePdfDownloadController; Api/ReportsController. Thin; health and reports are cross-cutting. Clean.
- **Jobs:** LogAuditEntry (ShouldQueue, queue=audit, tries/backoff). Audit write. Clean.
- **Landlord/**  
  Billing (Domain, Application, Infrastructure), Models (Tenant, Plan, Feature, Subscription, etc.), Services (StripeService, FeatureResolver, BillingAnalyticsService, FeatureUsageService), Http/Controllers, Policies, Events, Listeners. Responsibility: central platform. Clean separation from tenant.
- **Listeners:** OrderPaidListener, SendOrderConfirmationEmailListener; Financial (SyncFinancialOrderOnPaymentSucceeded, CreateFinancialTransaction, CreateLedgerTransactionOnOrderPaid, CreateLedgerReversalOnOrderRefunded, AuditLogOrderStatus); Invoice (CreateInvoiceOnOrderPaid). Responsibility: react to events. Idempotency in key listeners. Clean.
- **Models:** Mixed: Financial (FinancialOrder, FinancialOrderItem, FinancialOrderTaxLine, FinancialTransaction), Invoice (Invoice, InvoiceItem, etc.), Ledger (Ledger, LedgerAccount, LedgerTransaction, LedgerEntry), Refund (Refund), Promotion (Promotion, CouponCode, PromotionUsage), Currency (Currency, ExchangeRate, TenantCurrencySetting, etc.), Inventory (InventoryLocation, InventoryMovement, etc.), Customer (Customer, CustomerAddress), Invoice (CreditNote). Responsibility: Eloquent models for tenant (and some central). Smell: not under Modules; some duplication of “domain” concepts (e.g. FinancialOrder vs Order entity in Modules\Orders).
- **Observers:** Product, Order, StockItem, User, Plan, Feature, Subscription, Tenant → audit. Clean.
- **Policies:** Tenant-scoped (Product, Order, Customer, Invoice, Currency, Inventory) and Landlord (Plan, Tenant, Subscription). Used in Filament; not on API routes. Clear.
- **Providers:** AppServiceProvider (rate limiters, failed job handling, policies, observers), EventServiceProvider (event/listener and subscribe), TenancyServiceProvider, Filament panel providers. Standard.
- **Services:**  
  **Financial:** OrderLockService, OrderPaymentService, FinancialOrderSyncService, RefundService, PaymentSnapshotService, TaxCalculator.  
  **Invoice:** InvoiceService.  
  **Ledger:** LedgerService.  
  **Currency:** CurrencyService, ExchangeRateService, CurrencyConversionService, OrderCurrencySnapshotService.  
  **Promotion:** PromotionEvaluationService, PromotionResolverService, RecordPromotionUsageService.  
  **Reporting:** RevenueReportService, TaxReportService, TopProductsReportService, ConversionReportService.  
  **Customer:** CustomerPromotionEligibilityService.  
  **Inventory:** InventoryLocationService, InventoryAllocationService, InventoryTransferService.  
  Responsibility: application services and domain logic. Well-scoped; some live next to Models rather than under a module. Acceptable.

**app/Modules/**
- **Catalog:** Application (Handlers, Commands), Domain (Entities, Events), Infrastructure (Persistence), Http/Api (Controllers, Requests, Resources). Clear DDD layers. Clean.
- **Cart:** Same structure; ConvertCartHandler, OrderCreationService bridge to Orders. Clean.
- **Checkout:** Application (Commands, Contracts, DTOs, Exceptions, Services), Infrastructure (Services implementing contracts). Orchestrator depends on Cart, Order, Payment, Inventory, Promotion. Clean.
- **Orders:** Application (Commands, Handlers, Services, DTOs), Domain (Entities, Events, ValueObjects), Infrastructure (Persistence OrderModel, OrderItemModel). Clean.
- **Payments:** Application (Commands, Handlers, Services), Domain (Entities, Events, ValueObjects, Contracts), Infrastructure (Persistence, Gateways). PaymentSucceeded emitted from entity; repository publishes. Clean.
- **Inventory:** Application (Commands, Handlers, DTOs, Services), Domain (Repositories), Infrastructure (Persistence). Clean.

**app/Modules/Shared/**
- **Domain:** ValueObjects (Money, TenantId, etc.), Exceptions (CurrencyMismatchException, FeatureNotEnabledException, PaymentAlreadyProcessedException, FinancialOrderLockedException, InvalidValueObject, DomainException). Contracts (Command, DomainEvent). Single canonical Money; currency-strict. Clean.
- **Infrastructure:** Messaging (EventBus, LaravelEventBus), Persistence (TransactionManager). Used by all modules. Clean.

**Domain/** (within each module)
- Entities, ValueObjects, Events, Exceptions, Repositories (interfaces). No infrastructure imports. Dependency direction correct.

**Application/** (within each module)
- Commands, Handlers, DTOs, Contracts (e.g. CartService, OrderService). Handlers use repositories and domain entities. Clean.

**Infrastructure/** (within each module)
- Persistence (Eloquent repositories, models), Gateways (e.g. StripePaymentGateway). Implements domain interfaces. Clean.

**Services/** (app-level)
- As above. Responsibility: cross-module or non-module services (Financial, Invoice, Ledger, Currency, Promotion, Reporting). Separation is by feature; no circular dependency. Slight smell: “Services” is a broad bucket; could be grouped as Financial/, Promotion/, etc. (partially done).

**Listeners/** and **Events/**
- Events: Financial (OrderPaid, OrderLocked, OrderRefunded), JobFailed. Listeners: Financial, Invoice, OrderPaidListener, SendOrderConfirmationEmailListener, SubscriptionCancelledListener. Clear mapping in EventServiceProvider. Clean.

**Policies/**
- All under app/Policies (tenant) and app/Landlord/Policies. Consistent naming and registration. Clean.

**ValueObjects/**
- Canonical: app/Modules/Shared/Domain/ValueObjects/Money. No App/ValueObjects (removed). Currency enforced in Money (add/subtract same currency). Clean.

**Console/**
- Single command: retention:prune. Scheduler entry in routes/console.php. Clean.

**Filament/**
- Two panels; discoverResources/discoverWidgets for Tenant and Landlord. Many resources use modifyQueryUsing(…->with(...)) to avoid N+1. Smell: Landlord AuditLogResource (and possibly others) type incompatibility with Filament 3 blocks full test run.

---

## SECTION 3 — DOMAIN PRIMITIVES

**Money value object:**  
Single canonical: `App\Modules\Shared\Domain\ValueObjects\Money`.  
- Immutable, readonly. Constructor private.  
- `fromMinorUnits(int, string $currency)`, `getMinorUnits()`, `getCurrency()`, `equals`, `add`, `subtract`, `multiply`, `toArray`, `__toString`/`format`.  
- Deprecated aliases: `amountInMinorUnits()`, `currency()` for backward compatibility.  
- Arithmetic throws `CurrencyMismatchException` on mismatch. No float; integer minor units only.

**Currency logic:**  
- Tenant: CurrencyService (getTenantBaseCurrency, listEnabledCurrencies, ensureMultiCurrencyAllowed → throws FeatureNotEnabledException), ExchangeRateService (getCurrentRate, getRateAt, setManualRate), CurrencyConversionService (convert, convertWithSnapshot; rounding by tenant strategy). OrderCurrencySnapshotService fills financial_orders base/display/snapshot on lock. PaymentSnapshotService fills payments (payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base) on confirmation.  
- All monetary amounts in services use Money or integer minor units; conversion returns Money or structured array with converted minor units.

**Snapshot logic:**  
- **FinancialOrder:** snapshot JSON (items, tax_lines, applied_promotions, locked_at, currency, totals) built in OrderLockService.buildSnapshot; immutable after lock (booted() guard).  
- **Order (operational):** applied_promotions JSON; copied to FinancialOrder on sync; passed to lock for snapshot.  
- **Invoice:** snapshot from order; locked at issue; immutability guard in booted().  
- **Payment:** payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base set on confirm; guard prevents change when status=succeeded.

**Immutable models:**  
- **FinancialOrder:** updating() reverts financial/snapshot fields if status !== draft.  
- **Invoice:** updating() reverts totals/snapshot if status already issued/paid/... or locked_at set.  
- **PaymentModel:** updating() reverts snapshot fields if status === succeeded.  
- Ledger entries: no update API; append-only.

**Domain exceptions:**  
- CurrencyMismatchException, InvalidValueObject (Shared).  
- FeatureNotEnabledException (forFeature, forLimit), PaymentAlreadyProcessedException, FinancialOrderLockedException (Shared).  
- Used in Money, CurrencyService, InventoryLocationService, CreateProductHandler, ConfirmPaymentHandler; API returns 403 for FeatureNotEnabledException.

**Evaluation:**  
- **Money unified:** Yes; single class in Shared; no duplicate in App\ValueObjects.  
- **Float usage:** Only where required: percentages (e.g. tax rate, promotion percentage), exchange rate (decimal), rounding helpers (CurrencyConversionService::round). All money storage and Money VO use integers.  
- **Duplicated primitives:** None for Money.  
- **Currency enforced:** Strict in Money (add/subtract); CurrencyConversionService used for cross-currency; FeatureNotEnabledException when multi_currency not enabled and conversion/settings accessed.

---

## SECTION 4 — FINANCIAL INTEGRITY LAYER

**FinancialOrder structure:**  
- Tables: financial_orders (subtotal_cents, tax_total_cents, discount_total_cents, total_cents, currency, base_currency, display_currency, exchange_rate_snapshot, *_base_cents, *_display_cents, status, snapshot, locked_at, paid_at), financial_order_items, financial_order_tax_lines.  
- Sync from OrderModel (FinancialOrderSyncService) on payment success; then lock (tax + currency snapshot + applied_promotions in snapshot). Immutability guard when status !== draft.  
- **Safe:** Single financial aggregate per operational order after sync; lock path uses TaxCalculator and OrderCurrencySnapshotService; snapshot and totals immutable after lock.

**Payment snapshot enforcement:**  
- PaymentModel: payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base. PaymentSnapshotService.fillSnapshot on confirmation (multi-currency: convertWithSnapshot; else rate 1).  
- booted() on PaymentModel: when status === succeeded, revert any change to snapshot fields.  
- **Safe:** Snapshot set once on confirm; then protected.

**Invoice immutability:**  
- InvoiceService.issue sets status=issued, locked_at.  
- Invoice booted(): if status already issued/paid/partially_paid/refunded or locked_at set, revert subtotal/tax/discount/total/currency/snapshot on update.  
- **Safe:** No change to totals or snapshot after issue.

**Ledger system:**  
- Implemented. Ledgers, ledger_accounts, ledger_transactions, ledger_entries. LedgerService.createTransaction(..., entries) with validateBalanced (debits = credits).  
- CreateLedgerTransactionOnOrderPaidListener: Debit CASH, Credit REV, Credit TAX. CreateLedgerReversalOnOrderRefundedListener: Credit CASH, Debit REV, Debit TAX (proportional).  
- **Safe:** Balanced transactions only; entries immutable; reference to financial_order/refund.

**Double-entry correctness:**  
- validateBalanced throws if sum(debits) !== sum(credits). Entries have type (debit/credit) and amount_cents (non-negative).  
- **Safe:** No unbalanced transaction can be created.

**Refund handling:**  
- RefundService: validates refundable (paid - refunded), creates Refund record, creates FinancialTransaction TYPE_REFUND, updates Refund (status, financial_transaction_id), sets FinancialOrder status REFUNDED, dispatches OrderRefunded.  
- CreateFinancialTransactionListener (OrderRefunded): idempotent (skips if same order/amount/type REFUND already exists). CreateLedgerReversalOnOrderRefundedListener: reversing entries.  
- **Safe:** Over-refund prevented; ledger reversal; idempotent financial transaction.

**Idempotency protection:**  
- ConfirmPaymentHandler: throws PaymentAlreadyProcessedException if payment already succeeded.  
- SyncFinancialOrderOnPaymentSucceededListener: skips if FinancialOrder already paid.  
- CreateInvoiceOnOrderPaidListener: skips if invoice already exists for order_id.  
- CreateFinancialTransactionListener (OrderPaid): skips if completed credit transaction for order exists; (OrderRefunded): skips if REFUND transaction for order+amount exists.  
- **Safe:** Double payment confirm and duplicate invoice/transaction prevented.

**What is risky:**  
- If EventBus is null in EloquentPaymentRepository, PaymentSucceeded is never dispatched and financial sync never runs (dependency injection must provide LaravelEventBus).  
- Partial refunds: proportional REV/TAX reversal is rounded; large refunds could leave minor rounding skew (acceptable in practice).

**What is missing:**  
- No automated reconciliation job (e.g. ledger vs financial_transactions).  
- No explicit “invoice always created for paid order” contract test that runs in CI (covered by CheckoutToInvoiceFlowTest but suite may be blocked by Filament).

---

## SECTION 5 — TENANCY & ISOLATION

**How tenancy is implemented:**  
- Stancl Tenancy; database-per-tenant. Central DB: users, tenants, domains, plans, features, subscriptions, stripe_events, landlord_audit_logs. Tenant DBs: all ecommerce, financial, invoice, ledger, promotion, customer, inventory data.  
- Bootstrappers: Database, Cache, Filesystem, Queue switch to tenant connection/prefix when tenant is initialized.  
- API v1: InitializeTenancyBySubdomain. Filament Tenant: InitializeTenancyByDomain. Central domains (e.g. localhost) do not initialize tenant.

**Tenant isolation guarantees:**  
- Each tenant has a dedicated DB; no shared tables for business data. tenant_id on tenant tables and scopeForTenant/where('tenant_id', ...) used in queries.  
- FeatureResolver and BillingAnalyticsService read central DB (plan, features, subscriptions) when called in tenant context; they do not write to tenant DB from central.  
- LedgerService.getOrCreateLedgerForTenant(tenantId) creates ledger in current connection (tenant); no cross-tenant writes.

**Cross-tenant leakage risks:**  
- Currencies and exchange_rates tables are in tenant DB but have no tenant_id (shared reference data per tenant DB). Low risk if each tenant DB is isolated.  
- If a bug passed another tenant’s ID into a query without scopeForTenant, data could leak; code consistently uses tenant('id') or model scopes.  
- API: tenant is inferred from subdomain/domain only; no tenant_id in body. Ensure subdomain resolution is correct in production.

**Feature-limit enforcement layer:**  
- Helpers: tenant_feature('multi_currency'), tenant_limit('products_limit'). Implemented via FeatureResolver (central DB).  
- **API-level:** CreateProductHandler throws FeatureNotEnabledException forLimit('products_limit'). ProductController returns 403. CurrencyService.ensureMultiCurrencyAllowed throws forFeature('multi_currency'). InventoryLocationService throws forFeature('multi_location_inventory') when creating second location.  
- **Filament:** ProductResource/CreateProduct, BillingPage, ExchangeRateResource, CurrencyResource, etc. use tenant_feature/tenant_limit and canCreate/registerNavigation.  
- **Conclusion:** Feature enforcement is both API and UI; service layer is tenant-safe when tenant context is set (middleware/domain).

---

## SECTION 6 — PERFORMANCE & SCALABILITY

**Queue usage:**  
- Default connection: env QUEUE_CONNECTION (database/redis). Named queues: audit (LogAuditEntry), default (OrderPaidListener, SendOrderConfirmationEmailListener).  
- CreateInvoiceOnOrderPaidListener, CreateFinancialTransactionListener, AuditLogOrderStatusListener, CreateLedgerTransactionOnOrderPaidListener, CreateLedgerReversalOnOrderRefundedListener, SyncFinancialOrderOnPaymentSucceededListener run **synchronously** to preserve tenant context.  
- LogAuditEntry: tries=3, backoff=[5,30,60]. Queue::failing in AppServiceProvider logs and dispatches JobFailed event.

**Horizon:**  
- config/horizon.php present; supervisor groups for default, audit, financial, billing; production/local envs; worker counts and timeouts documented.

**Indexing strategy:**  
- Tenant migrations: indexes on tenant_id, status, order_id, (order_id, status) for invoices and payments, (tenant_id, status) for financial_orders, operational_order_id, created_at; promotions (tenant_id, is_active, starts_at, ends_at), coupon code, promotion_usages (promotion_id, customer_id/email); ledger (ledger_id, transaction_at); inventory (product_id, location_id, created_at).  
- Composite indexes where filters combine (e.g. order_id + status). Reasonable for current query patterns.

**N+1 risks:**  
- Filament: many resources use modifyQueryUsing(…->with(['relation'])) (e.g. InvoiceResource with customer/order, OrderResource with items, SubscriptionResource with tenant/plan).  
- Reporting services use aggregate queries (sum, count) or single-query + in-memory grouping (TopProductsReportService).  
- Checkout and SyncFinancialOrderOnPaymentSucceededListener load order with items.  
- **Verdict:** Mitigated in critical list/detail views; not every relation audited.

**Caching:**  
- Revenue/Tax/TopProducts/Conversion reports: Cache::remember, TTL 300s (5 min).  
- CurrencyService.getSettings: Cache::remember (tenant_currency_settings), TTL from config.  
- Exchange rates: cached in ExchangeRateService.  
- BillingAnalyticsService (MRR, churn, plan distribution): TTL 300s.  
- No cache for mutable financial aggregates.

**Retention strategy:**  
- config/retention.php: audit_days, inventory_movement_days, stripe_events_days.  
- RetentionPruneCommand: prune tenant audit logs, inventory_movements (per tenant), landlord stripe_events; dry-run option.  
- Scheduler: retention:prune daily at 02:00.  
- Financial records (orders, invoices, ledger, financial_transactions) not pruned.

**Readiness:**  
- **Small SaaS:** Adequate (DB queue, single app, retention and indexes in place).  
- **Growing SaaS:** Horizon + Redis queue recommended; monitor N+1 in new Filament pages; cache and indexes already support reporting.  
- **High GMV marketplace:** Not applicable (no marketplace/vendor); for high GMV single-tenant stores: scale DB, Redis, Horizon; consider read replicas for reporting; ledger and idempotency already support integrity.

---

## SECTION 7 — WALLET / MARKETPLACE

**Not implemented.**

- No vendor/seller entity.  
- No wallet or balance tracking for vendors.  
- Ledger has LedgerAccount::TYPE_PLATFORM_COMMISSION as a placeholder only; no commission calculation or payout workflow.  
- No refund adjustment logic for “vendor share” or marketplace commission.  
- **Conclusion:** Pure B2B SaaS (tenant = merchant); no marketplace or vendor payables.

---

## SECTION 8 — TESTING COVERAGE

**Feature tests:**  
- CheckoutToInvoiceFlowTest (checkout → payment confirm → financial order, invoice, financial_transaction, snapshot; idempotent second confirm throws PaymentAlreadyProcessedException).  
- FullFinancialIntegrityTest (multi-currency, snapshot, Money, double payment prevention).  
- Invoice: InvoiceFromOrderSnapshotTest, InvoiceLockedAfterIssuanceTest, InvoicePaymentBalanceTest, CreditNoteExceedsTotalTest, InvoiceNumberUniqueTest, InvoiceSnapshotImmutabilityTest, InvoicePdfGeneratedTest.  
- Financial: OrderCalculationTest, TaxCalculationTest, SnapshotImmutabilityTest, RefundOverpaymentTest.  
- Multi-Currency: EnableCurrency, SetExchangeRate, ConvertMoney, OrderSnapshotImmutability, PaymentDifferentCurrency, HistoricalRateConversion, FeatureLimitEnforcement, RoundingCorrectness.  
- Multi-Location Inventory: CreateLocation, AdjustStock, ReserveStock, PreventOverselling, TransferStock, CancelOrderReleasesStock, MovementLogInserted, MultiLocationFeatureLimit.  
- CustomerIdentity: Registration, Login, RateLimit, EmailVerification, OrderLinked, FirstPurchasePromotion, GuestCheckoutAndLink, AccountDeletion, Anonymize.  
- HealthEndpointTest, RateLimiterTest, IdempotentFinancialJobTest (OrderPaid twice → single invoice/transaction), TenantIsolationTest, StressCheckoutTest.  
- Audit, Rbac, TenantPanel, LandlordPanel, StripeWebhook, CheckoutFlow, PlanLimitEnforcement.

**Integration tests:**  
- Helpers: create_tenant_product, create_cart_and_add_item, do_checkout, confirm_payment, create_tenant_stock. Used by feature tests. No standalone “integration” suite label.

**Financial integrity tests:**  
- FullFinancialIntegrityTest, CheckoutToInvoiceFlowTest, RefundOverpaymentTest, SnapshotImmutabilityTest, TaxCalculationTest, OrderCalculationTest.  
- Invoice tests cover balance, credit note limit, snapshot immutability.

**Ledger balancing tests:**  
- Unit: LedgerServiceTest (validateBalanced accepts equal debit/credit; rejects unbalanced; rejects negative amount).  
- No feature test that creates a real ledger transaction and asserts balance in DB (covered indirectly by OrderPaid → CreateLedgerTransactionOnOrderPaidListener in flow tests).

**Multi-currency tests:**  
- ConvertMoneyTest, HistoricalRateConversionTest, PaymentDifferentCurrencyTest, RoundingCorrectnessTest, OrderSnapshotImmutabilityTest, FeatureLimitEnforcementTest, SetExchangeRateTest, EnableCurrencyTest.

**Idempotency tests:**  
- CheckoutToInvoiceFlowTest (second confirm throws); IdempotentFinancialJobTest (double OrderPaid → one invoice, one transaction).

**Weak areas:**  
- Full suite may not run: Filament type errors (e.g. AuditLogResource $navigationGroup) cause fatal on bootstrap in some environments.  
- No dedicated API auth tests (unauthenticated vs authenticated) for checkout/orders/payments.  
- No end-to-end test that explicitly asserts ledger row counts and balance after payment and after refund.  
- Promotion engine: PromotionEvaluationServiceTest (unit) exists; no full feature test with DB promotions and checkout with coupon codes.

---

## SECTION 9 — PRODUCTION READINESS SCORE

| Dimension | Score (0–100) | Explanation |
|-----------|----------------|--------------|
| **Financial safety** | 82 | Single Money VO, integer minor units, snapshots on order/payment/invoice, immutability guards, idempotency on payment and invoice/transaction, balanced ledger, refund validation. Risk: EventBus must be bound; rounding in proportional refund. |
| **Architecture cleanliness** | 78 | Clear modules and DDD in Modules; Shared single place for Money and exceptions; financial flow unified (PaymentSucceeded → sync/lock/markPaid → OrderPaid). Services/Models outside Modules are organized by feature. Some duplication (e.g. Order entity vs FinancialOrder) by design (operational vs financial). |
| **Scalability** | 65 | DB-per-tenant scales with tenant count but increases ops. Queue/Horizon configured; critical financial listeners sync. Indexes and retention in place. No sharding or read replicas in code. |
| **Maintainability** | 80 | Commands/Handlers, services, repositories, events; documentation (PHASE3, PHASE4, audit). Feature flags and limits centralized. Filament type issues and two “Order” concepts require onboarding. |
| **Marketplace readiness** | 0 | No vendor, wallet, commission, or payout. Not applicable for current product. |
| **Investor readiness** | 72 | Strong financial and tenant story; ledger and reporting in place; roadmap (phases) documented. Gaps: API auth, full test suite green, central_domains from env, optional API versioning and runbooks. |

**Overall (equal weight, excluding marketplace):** (82+78+65+80+72)/5 ≈ **75/100**.

---

## SECTION 10 — WHAT IS DONE VS WHAT IS MISSING

**DONE:**
- Database-per-tenant (Stancl); central and tenant migrations; provisioning.
- Catalog (products, categories); API + Filament; products_limit enforced in API and Filament.
- Cart (create, add/update/remove item, convert); API; Shared Money.
- Checkout: create order from cart, promotion evaluation (percentage/fixed/threshold/BOGO, stackable/exclusive, usage limits), discount and applied_promotions on order, create payment intent; inventory reserve/allocate (single and multi-location); coupon codes optional.
- Orders: OrderModel, order_items; create, add item, confirm, pay, ship, cancel; domain Order + repository; link to customer_id and customer_email.
- Payments: create/confirm; Payment entity; Stripe gateway; PaymentSucceeded on save; ConfirmPaymentHandler idempotent (PaymentAlreadyProcessedException).
- Financial: FinancialOrder sync from OrderModel on PaymentSucceeded; OrderLockService (tax, snapshot, OrderCurrencySnapshotService); OrderPaymentService.markPaid; OrderPaid event; immutability guard on FinancialOrder.
- Invoice: create from FinancialOrder, issue, applyPayment, credit note, PDF; immutability guard; auto-create on OrderPaid (idempotent).
- Financial transactions: CreateFinancialTransactionListener (credit on OrderPaid, refund on OrderRefunded); idempotent.
- Ledger: ledgers, accounts (REV, TAX, CASH, AR, REFUND); LedgerService (createTransaction, validateBalanced); ledger tx on OrderPaid; reversing tx on OrderRefunded.
- Refund: Refund model; RefundService (validate, create Refund + FinancialTransaction, OrderRefunded); ledger reversal; idempotent listener.
- Payment snapshot: PaymentSnapshotService on confirm; PaymentModel guard on snapshot fields.
- Multi-currency: CurrencyService, ExchangeRateService, CurrencyConversionService, OrderCurrencySnapshotService; tenant_currency_settings; feature-gated.
- Multi-location inventory: locations, stocks, movements, reservations, transfers; allocation in checkout; feature-gated.
- Customer identity: customers, addresses, Sanctum auth:customer, API auth; guest link; GDPR export/delete.
- Promotions: promotions, coupon_codes, promotion_usages; PromotionEvaluationService (pure), PromotionResolverService; usage recorded on payment; snapshot in order and financial order.
- Reporting: RevenueReportService, TaxReportService, TopProductsReportService, ConversionReportService; cached; API /api/v1/reports/revenue, tax, products, conversion.
- Filament Tenant: resources and widgets (revenue, orders, conversion, AOV, top products, usage vs plan); Landlord: MRR, subscriptions, RevenueByPlan; BillingAnalyticsService, FeatureUsageService.
- Rate limiting: checkout, payment, webhook, login, customer-register/login/forgot/reset; applied on routes.
- Retention: config and RetentionPruneCommand; scheduler.
- Health: /health (DB, cache, queue).
- RBAC: Spatie; Landlord and Tenant roles/permissions; policies in Filament.
- Audit: tenant and landlord logs; observers; LogAuditEntry job; prune.
- Tests: feature tests for checkout→invoice, financial integrity, idempotency, tenant isolation, stress; unit for Money, PromotionEvaluationService, LedgerService.

**MISSING:**
- API authentication for storefront (checkout, orders, payments, catalog, inventory): no auth middleware on these routes; only customer auth on customer/*.
- Landlord API auth: billing routes (plans, subscribe, etc.) unauthenticated except webhook.
- Central domains from env: central_domains in config are hardcoded; no CENTRAL_DOMains env.
- Filament 3 type fixes: Landlord resources (e.g. AuditLogResource) $navigationGroup type so test suite can run without fatal.
- Contract test that checkout→invoice and ledger run in CI without Filament bootstrap (or fix Filament so full suite runs).
- Vendor/wallet/marketplace: not in scope for current product.
- Read replicas / central DB scaling: not in code.
- API versioning contract: only path prefix /api/v1; no formal versioning doc or header contract.

---

## SECTION 11 — TOP 10 ARCHITECTURAL RISKS

1. **API v1 unauthenticated:** Checkout, orders, payments, catalog, inventory are callable by anyone who can reach the tenant subdomain. High abuse and fraud risk for production.
2. **EventBus null:** If LaravelEventBus is not bound or repository is constructed with null EventBus, PaymentSucceeded is never dispatched and financial sync (invoice, ledger, financial transaction) never runs. Ensure binding and tenant context in all code paths that save Payment.
3. **Filament type errors block tests:** Fatal on AuditLogResource (or similar) prevents full test suite from running; regressions in financial or checkout flow may go undetected.
4. **Two “order” concepts:** Operational (OrderModel) and Financial (FinancialOrder) are documented and bridged by SyncFinancialOrderOnPaymentSucceededListener, but new developers must understand the split and that only the sync path creates invoices/ledger.
5. **Central domains hardcoded:** Production central domains require code/config change; should be env-driven.
6. **Landlord API unprotected:** Plans and subscription endpoints callable without auth; could allow unauthorized subscribe/cancel if exposed.
7. **Rounding in refund ledger:** Proportional REV/TAX reversal uses round(); very large refunds could theoretically leave a 1-cent skew; acceptable but worth documenting.
8. **N+1 not fully audited:** Filament and reporting have been partially optimized with with(); new resources or list pages could introduce N+1.
9. **Queue driver default:** Default queue is database; for production scale Redis + Horizon should be explicit and documented so workers and failure handling are reliable.
10. **No formal reconciliation:** No automated job comparing ledger totals to financial_transactions or invoice totals; operational reliance on correct listener execution and idempotency.

---

## SECTION 12 — STRATEGIC NEXT MOVE

**Next technical phase:**  
- **Secure API and fix test suite.** Add auth (e.g. Sanctum API token or bearer) to api/v1 checkout, orders, payments, and optionally catalog/inventory; document storefront client contract. Fix Filament Landlord resource types so the full test suite runs. Move central_domains to env. Add a smoke or contract test that runs checkout→payment→invoice→ledger without requiring full Filament bootstrap so CI can assert financial pipeline.

**Next business expansion phase:**  
- **Promotions and reporting in production.** Promotions and reporting APIs are implemented; validate with real tenants (coupon usage, report caching, dashboard widgets). Optionally add “trial conversion” and “churn” metrics to landlord dashboard if not already exposed. No marketplace/vendor in scope unless product strategy changes.

**Risk reduction priority:**  
1. **Critical:** API auth for checkout/orders/payments; EventBus binding verified in all environments.  
2. **High:** Filament type fixes and green test suite; central_domains from env.  
3. **Medium:** Landlord API auth; explicit Redis+Horizon for production; N+1 audit for new Filament pages.  
4. **Low:** Reconciliation job (ledger vs financial_transactions); formal API versioning doc.

---

*End of complete technical audit. Conclusions are from codebase analysis only; no code was modified.*
