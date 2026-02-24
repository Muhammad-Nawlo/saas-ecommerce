# Complete Technical Audit — Multi-Tenant SaaS E-Commerce Platform

**Audit date:** 2026-02-24 (updated)  
**Scope:** Full codebase analysis — no code changes.  
**Assumption:** Project will handle significant GMV; assessment is CTO-grade for production and funding readiness.

---

## 1. Executive Summary

The platform is a **Laravel 11 modular monolith** with **database-per-tenant** (Stancl Tenancy), Filament 3 for Landlord and Tenant panels, and a clear split between ecommerce modules (Catalog, Cart, Checkout, Orders, Payments, Inventory) and financial/operational features (Financial Orders, Tax, Invoicing, Multi-Location Inventory, Multi-Currency). **Critical finding:** There are **two disconnected order systems** — the API checkout flow creates and pays **orders** (OrderModel / `orders` table), while Invoicing, Tax, and Financial Order Lock/Pay operate on **financial_orders** (FinancialOrder). No bridge exists from checkout → FinancialOrder, so **invoices are never auto-created from API checkout**, and financial/tax modeling is manual/Filament-only. Additional risks: API v1 checkout/orders have **no authentication**; **two Money value objects** (Shared vs App) with different APIs; **OrderLockService** does not call **OrderCurrencySnapshotService** (currency snapshot columns never filled on lock); **Filament type errors** prevent full test suite from running. Strengths: solid tenant isolation, good RBAC and policies in Filament, strong inventory allocation and reservation design, comprehensive migrations and docs for newer features (invoicing, multi-location, multi-currency).

---

## 2. Module Status Matrix

| Module | Status | Completion % | Notes | Risk Level |
|--------|--------|--------------|-------|------------|
| **Tenancy (Stancl)** | Mostly Implemented | 90 | DB-per-tenant, bootstrappers, domain/subdomain; central_domains hardcoded | Medium |
| **Catalog (Products/Categories)** | Mostly Implemented | 85 | API + Filament; categories Filament-only; product limit enforced | Low |
| **Cart** | Mostly Implemented | 85 | API CRUD, convert to order; uses Shared Money | Low |
| **Checkout** | Partially Implemented | 65 | Creates OrderModel only; no FinancialOrder; no auth on routes | **High** |
| **Orders (ecommerce)** | Mostly Implemented | 80 | OrderModel, confirm/pay/cancel/ship; linked to inventory; no link to financial_orders | **High** |
| **Financial Orders** | Partially Implemented | 70 | Full model, lock, tax, snapshot; not created from checkout; currency snapshot not filled on lock | **High** |
| **Payments (tenant)** | Mostly Implemented | 75 | Create/confirm/refund; PaymentModel; ConfirmPayment marks orders table paid; no financial_transactions from API flow | **High** |
| **Financial Transactions** | Partially Implemented | 60 | Table + listener for OrderPaid(FinancialOrder); only fired from Filament markPaid, not from API payment | **High** |
| **Tax (tenant)** | Mostly Implemented | 85 | TaxRate, TaxCalculator, OrderLockService snapshots tax; FinancialOrder-only | Medium |
| **Invoicing** | Mostly Implemented | 80 | Invoice from FinancialOrder, issue/pay/credit/void, PDF; auto-create only on Financial\OrderPaid (Filament path) | **High** |
| **Multi-Location Inventory** | Mostly Implemented | 85 | Locations, stocks, movements, reservations, transfers; allocation in checkout; feature-gated | Medium |
| **Multi-Currency** | Partially Implemented | 65 | Currencies, rates, conversion, settings; OrderCurrencySnapshotService never called from OrderLockService; payment/invoice snapshot columns unused in API flow | **High** |
| **Customer Identity** | Mostly Implemented | 85 | Customers, addresses, Sanctum auth:customer, API auth; link guest orders; GDPR export/delete | Low |
| **Promotions** | Skeleton Only | 15 | CustomerPromotionEligibilityService stub; no engine, no rules applied at checkout | Medium |
| **Landlord Billing** | Mostly Implemented | 85 | Plans, features, subscriptions, Stripe webhook, idempotency; checkout/portal | Medium |
| **RBAC (tenant)** | Mostly Implemented | 85 | Spatie, TenantRole, TenantPermissions, policies, TenantRoleSeeder; API routes do not use permission middleware | Medium |
| **RBAC (landlord)** | Mostly Implemented | 80 | LandlordRole, super_admin, policies; Filament enforced | Low |
| **Audit Logging** | Mostly Implemented | 85 | Tenant + landlord logs, observers, LogAuditEntry job, prune command; Filament read-only | Low |
| **Feature Limits** | Mostly Implemented | 80 | tenant_feature(), tenant_limit(), FeatureResolver; products_limit in ProductResource; multi_currency / multi_location_inventory gating | Low |
| **API (v1)** | Partially Implemented | 60 | Catalog, cart, checkout, orders, payments, inventory, customer — **no auth** on checkout/orders/catalog/cart/payments/inventory | **High** |
| **Filament Tenant** | Mostly Implemented | 80 | Many resources; Filament 2/3 type issues in Landlord (AuditLogResource etc.) break test bootstrap | High |
| **Filament Landlord** | Partially Implemented | 70 | Tenant, Plan, Feature, Subscription, Audit; Stripe webhook verified; type errors block tests | High |
| **Queue / Jobs** | Partially Implemented | 60 | LogAuditEntry, ShouldQueue on some listeners; default database driver; no Horizon config in repo | Medium |
| **Tests** | Partially Implemented | 55 | Good feature tests for Financial, Invoice, Customer, Multi-Location, Multi-Currency; **suite fails on Filament type error**; no auth tests for API | High |

**Status legend:** Not Started | Skeleton Only | Partially Implemented | Mostly Implemented | Production Ready

---

## 3. Architecture Review

### 3.1 Domain-Driven Design

- **Modules (Catalog, Cart, Checkout, Orders, Payments, Inventory)** use Application/Domain/Infrastructure layers, Command/Handler pattern, and Repositories. Clear separation within each module.
- **Landlord Billing** has Domain/Application/Infrastructure and Stripe gateway abstraction.
- **Financial, Invoicing, Multi-Currency, Multi-Location** live in `app/Services`, `app/Models`, `app/Events` — not in Modules; mixed with Filament and domain concepts. **Inconsistency:** ecommerce “orders” vs “financial_orders” is a bounded context split without a unified facade or sync.

### 3.2 Separation of Concerns

- **Checkout** orchestrates cart → order (OrderModel) → payment; does not touch FinancialOrder or OrderLockService.
- **OrderPaymentService** and **OrderLockService** operate only on FinancialOrder; no integration with Modules\Orders or Modules\Payments.
- **Dual order systems** create a **separation of concerns violation**: business has two sources of truth for “order” (orders vs financial_orders) with no defined sync or single writer.

### 3.3 Dependency Direction

- **Modules** depend on Shared (ValueObjects, Audit, TransactionManager); some depend on app-level services (InventoryAllocationService, CheckoutInventoryService). **Feature resolution** (tenant_feature, tenant_limit) pulls Landlord FeatureResolver into tenant code — acceptable for SaaS but creates a cross-boundary dependency.
- **Infrastructure** correctly depends on Domain/Application; no Domain depending on Infrastructure.

### 3.4 Service Layer

- **OrderLockService**, **TaxCalculator**, **InvoiceService**, **CurrencyConversionService**, **InventoryAllocationService** are well-scoped and transactional where needed.
- **OrderCurrencySnapshotService** exists but is **never invoked** from OrderLockService or any other path — currency snapshot columns on financial_orders remain null unless called manually.

### 3.5 Transaction and Concurrency Safety

- **CheckoutOrchestrator**, **ConfirmOrderHandler**, **CancelOrderHandler**, **InventoryAllocationService**, **InventoryTransferService** use DB transactions and/or row locking. **InvoiceNumberGenerator** uses row locking. Good.
- **OrderPaymentService** uses a single DB::transaction; **InvoiceService** uses transactions for create/issue/applyPayment.

### 3.6 Event-Driven Architecture

- **OrderLocked** / **OrderPaid** (Financial) drive CreateFinancialTransactionListener and AuditLogOrderStatusListener; **OrderPaid** drives CreateInvoiceOnOrderPaidListener. All are **FinancialOrder**-based; **PaymentSucceeded** (Payments module) only triggers OrderPaidListener (log) and MarkOrderAsPaid for **orders** table. No event from API payment flow to FinancialOrder or Invoice.

### 3.7 Value Objects

- **Two Money types:** `App\ValueObjects\Money` (fromCents, convertWithRate) used by Invoice, Currency, Financial services; `App\Modules\Shared\Domain\ValueObjects\Money` (fromMinorUnits) used by Cart, Orders, Catalog, Payments. **Risk:** Boundary code (e.g. CheckoutOrchestrator) uses Shared Money for payment; no conversion at FinancialOrder boundary. **Inconsistency and potential bugs** if both are used in one flow.

### 3.8 Financial Immutability and Snapshots

- **FinancialOrder:** Lock sets snapshot, locked_at; no edits after lock in Filament. **Good.**
- **Invoice:** Issued state locks; totals from snapshot. **Good.**
- **OrderCurrencySnapshotService:** Not called from OrderLockService — **base_currency, display_currency, exchange_rate_snapshot, *_base_cents, *_display_cents** on financial_orders are never populated on lock. **Gap.**

### 3.9 Audit Integrity

- Observers and AuditLogger used for key models; LogAuditEntry job for async write. **Good.** No evidence of audit bypass in critical paths.

### 3.10 Soft Deletes

- Used on tenants, plans, features, subscriptions, products, orders, carts, customers, invoices, inventory_locations. Appropriate for audit and recovery. No misuse detected.

### 3.11 Over-coupling and Missing Abstractions

- **Checkout → OrderModel** is the only path; no abstraction for “create financial order from cart” or “sync order to financial order.” **Missing:** Single order aggregate or sync service that keeps orders and financial_orders aligned when payment is confirmed.
- **API** has no auth abstraction (e.g. optional API key + tenant) for storefront vs public.

---

## 4. Tenancy Safety Review

### 4.1 Database per Tenant

- **Confirmed:** Tenant DBs created via Stancl (CreateDatabase, MigrateDatabase on TenantCreated). Tenant connection switched by bootstrappers. **Isolation is correct.**

### 4.2 Tenant ID Leakage

- **tenant_id** on tenant tables (products, orders, financial_orders, invoices, customers, inventory_locations, etc.) and scopes (e.g. scopeForTenant, getEloquentQuery) used in Filament. **Currencies / exchange_rates** are **tenant-shared** (no tenant_id in schema); tenant_currency_settings and tenant_enabled_currencies are tenant-scoped. **Risk:** Exchange rates are global; any tenant can see any rate. **Design choice:** Likely intentional (shared reference data); document and ensure no tenant-specific rate leakage in UI.

### 4.3 Landlord vs Tenant Boundary

- **Central DB:** users, tenants, domains, plans, features, subscriptions, stripe_events, landlord_audit_logs, idempotency_keys. **Tenant DB:** all ecommerce and tenant-specific data. **No cross-DB FKs.** FeatureResolver reads from central (plan/features) and is used in tenant context — acceptable.

### 4.4 Feature-Limit Enforcement

- **tenant_feature('multi_currency')** and **tenant_feature('multi_location_inventory')** used in services and Filament (shouldRegisterNavigation, canCreate). **tenant_limit** used for products_limit. **Consistent** where applied; **not applied** to API (e.g. no limit check on product creation via API).

### 4.5 Filament Panel Separation

- **Landlord** panel for tenants, plans, subscriptions, audit; **Tenant** panel for store-specific resources. **Tenant panel** correctly scoped to tenant connection; **Landlord** uses central. **Good.**

---

## 5. Financial Safety Review

### 5.1 Money as Integer Minor Units

- **orders:** total_amount (integer); **order_items:** unit_price_amount, total_price_amount (integer). **financial_orders:** subtotal_cents, tax_total_cents, total_cents (bigInteger). **invoices:** subtotal_cents, etc. **payments:** amount (unsignedBigInteger). **Good.** No float/decimal for money in core tables.

### 5.2 Exchange Rate Snapshots

- **financial_orders:** exchange_rate_snapshot, base_currency, display_currency, *_base_cents, *_display_cents columns exist but **OrderLockService does not call OrderCurrencySnapshotService** — they remain null. **Payment/invoice** snapshot columns exist; **payment flow** (Payments module) does not write to financial_orders or payment_amount_base / exchange_rate_snapshot on payments table. **Incorrect use:** Snapshots not used in the main checkout/payment path.

### 5.3 No Dynamic Recalculation of Old Orders

- **FinancialOrder** and **Invoice** are immutable after lock/issue; totals from snapshot. **Good.** (orders table is recalculated on confirm — different flow.)

### 5.4 Currency Mixing

- **Money** value objects throw on currency mismatch in add/subtract. **CurrencyConversionService** converts with snapshot. **Risk:** Two Money VOs; if a module passes wrong type or currency at boundary, errors could be late or unclear.

### 5.5 Mutable Financial Records

- **FinancialOrder** and **Invoice** are protected after lock/issue in Filament. **financial_transactions** are append-only. **Good.**

### 5.6 Payment Consistency

- **ConfirmPaymentHandler** marks **orders** table order as paid and saves payment (PaymentModel). **OrderPaymentService::markPaid(FinancialOrder)** is **only** called from Filament ViewInvoice; it updates **financial_orders** and dispatches **OrderPaid** (Financial). **No link** between PaymentModel (payments table) and FinancialOrder. **Risk:** Payment can succeed in Stripe and in payments table without any FinancialOrder or invoice being created or paid.

### 5.7 Invoice Immutability

- **InvoiceService** locks on issue; no edit of totals after. **Good.**

---

## 6. Inventory Safety Review

### 6.1 Reservation System

- **Multi-location:** InventoryReservation rows; **allocateStock(Order)** reserves by order_id; **releaseReservation** / **confirmReservation** used on cancel and confirm. **Single-location:** stock_items.reserved_quantity; reserve/release in InventoryStockService. **Correct.**

### 6.2 Overselling Prevention

- **validateStock** before checkout; **allocateStock** uses lockForUpdate and checks quantity >= reserved + requested. **PreventOversellingTest** exists. **Good.**

### 6.3 Movement Ledger

- **inventory_movements** append-only; **InventoryMovementLogger** used by adjustment and transfer services. **Good.**

### 6.4 Transfer Atomicity

- **InventoryTransferService** runs in DB::transaction; decrease source, increase destination, movements. **Good.**

### 6.5 Negative Stock Protection

- **InventoryAllocationService** and **InventoryStockAdjustmentService** check for negative quantity. **Good.**

### 6.6 Low Stock Detection

- **LowStockDetected** event dispatched from **InventoryStockAdjustmentService**; **LowStockLocationsWidget** and **OutOfStockProductsWidget** exist. **Good.**

---

## 7. Security Review

### 7.1 RBAC Enforcement

- **Filament** uses policies (Product, Order, Customer, Invoice, Inventory, Currency, etc.) and permission checks. **TenantRoleSeeder** assigns VIEW_*/MANAGE_* to roles. **Good.**

### 7.2 Policy Usage

- **Policies** registered in AppServiceProvider for ProductModel, OrderModel, Customer, Invoice, Currency, InventoryLocation, StockItemModel; Landlord for Plan, Tenant, Subscription. **API controllers** do **not** use policy checks — only tenancy middleware. **Gap.**

### 7.3 Missing Authorization

- **api/v1:** No auth on catalog, cart, checkout, orders, payments, inventory. **Customer** routes use auth:customer. **Landlord api/landlord:** Billing routes (plans, subscribe, etc.) have **no auth** in route files; webhook is public by design. **Risk:** Unauthenticated access to create orders, payments, and checkout.

### 7.4 Public Route Exposure

- **Checkout** and **orders** are public by subdomain; any client can POST checkout and confirm payment if they know or guess IDs. **Critical for production.**

### 7.5 Mass Assignment

- **Models** use $fillable; **FinancialOrder**, **Invoice**, **OrderModel** restrict fields. **No obvious mass-assignment exposure** in audited code.

### 7.6 Validation

- **FormRequests** used for API and customer endpoints. **Good.** Not every endpoint re-audited for strictness.

### 7.7 Payment Webhook Verification

- **StripeWebhookController** verifies signature with config('services.stripe.webhook_secret'). **Idempotency** via stripe_events table. **Good.**

### 7.8 API Rate Limiting

- **Customer** register/login/forgot/reset use throttle names. **General API** (checkout, orders, catalog, cart, payments) has **no rate limiting** in route files. **Risk:** DoS and abuse.

---

## 8. Performance Review

### 8.1 DB Indexes

- **Tenant migrations** include indexes on tenant_id, status, order_id, invoice_id, (tenant_id, status), (tenant_id, email), (product_id, location_id), etc. **Reasonable coverage.** No full audit of every query path.

### 8.2 N+1 Risk

- **Filament** relation managers and tables use with() in some places; **InventoryAllocationService** loads order with items. **Potential N+1** in list views (e.g. orders with items, invoices with items) if not eager-loaded — not fully verified.

### 8.3 Large Table Growth

- **tenant_audit_logs**, **landlord_audit_logs**, **inventory_movements** are append-only. **PruneAuditLogsCommand** exists. **Movement** table has no prune in repo — may grow unbounded.

### 8.4 Caching

- **ExchangeRateService::getCurrentRate** cached; **Spatie Permission** cached. **No cache** for tenant settings, feature flags, or product/stock lists in API.

### 8.5 Dashboard Queries

- **RevenueByCurrencyWidget** uses FinancialOrder::query()->selectRaw(...)->groupBy('currency') — **groupBy on Eloquent** can be fragile; **CurrencyDistributionWidget** runs multiple queries. **Acceptable** for small/medium tenants; may need optimization at scale.

### 8.6 Eager Loading

- **OrderModel::with('items')** used in allocation and checkout. **Filament** resources vary; some use ->with() in modifyQueryUsing. **Not consistently audited.**

---

## 9. Test Coverage Review

### 9.1 Unit Tests

- **MoneyTest** (Unit) for App\ValueObjects\Money. **Shared Money** and other domain units not fully covered.

### 9.2 Feature Tests

- **Financial:** OrderCalculationTest, TaxCalculationTest, SnapshotImmutabilityTest, RefundOverpaymentTest.
- **Invoice:** InvoiceFromOrderSnapshotTest, InvoiceLockedAfterIssuanceTest, InvoicePaymentBalanceTest, CreditNoteExceedsTotalTest, InvoiceNumberUniqueTest, InvoiceSnapshotImmutabilityTest, InvoicePdfGeneratedTest.
- **CustomerIdentity:** Registration, Login, RateLimit, EmailVerification, OrderLinked, FirstPurchasePromotion, GuestCheckoutAndLink, AccountDeletion, Anonymize.
- **Multi-Location Inventory:** CreateLocation, AdjustStock, ReserveStock, PreventOverselling, TransferStock, CancelOrderReleasesStock, MovementLogInserted, MultiLocationFeatureLimit.
- **Multi-Currency:** EnableCurrency, SetExchangeRate, ConvertMoney, OrderSnapshotImmutability, PaymentDifferentCurrency, HistoricalRateConversion, FeatureLimitEnforcement, RoundingCorrectness.
- **Audit, Rbac, TenantPanel, LandlordPanel, StripeWebhook, CheckoutFlow, PlanLimitEnforcement** present.

### 9.3 Critical Path Coverage

- **Order flow (API):** CheckoutFlowTest exists; **FinancialOrder** path not tested end-to-end (no test that checkout creates FinancialOrder or that payment confirms it).
- **Payment flow:** Stripe webhook and payment confirm tested in isolation; **no test** that ConfirmPayment → OrderPaid (Financial) or Invoice creation.
- **Inventory reservation:** Covered by Multi-Location tests.
- **Currency conversion:** Covered by Multi-Currency tests.
- **Feature limits:** PlanLimitEnforcement, MultiLocationFeatureLimit, Multi-Currency feature limit tests.
- **Tenant isolation:** Tests use Tenant::create and tenancy()->initialize; no cross-tenant tests in the list.

### 9.4 High-Risk Untested Areas

- **Checkout → FinancialOrder / Invoice** (no bridge; no test).
- **API auth** (no tests for unauthenticated vs authenticated access).
- **OrderPaymentService** and **CreateInvoiceOnOrderPaidListener** only triggered from Filament path; **no test** that API payment triggers them (they don’t).
- **Full test suite** fails on **Filament** type error (AuditLogResource etc.), so **many tests cannot run** in current state.

---

## 10. Technical Debt List

### Quick Fixes

1. **Call OrderCurrencySnapshotService::fillSnapshot in OrderLockService** after building snapshot (and ensure tenant context is set) so base_currency, display_currency, exchange_rate_snapshot and base/display amounts are populated on lock.
2. **Fix Filament Resource type incompatibility** (e.g. AuditLogResource, FeatureResource, TenantResource, PlanResource, SubscriptionResource) — align `$navigationGroup` / `$navigationIcon` and method signatures with Filament 3 so the test suite can run.
3. **Move central_domains** to env (e.g. CENTRAL_DOMAINS) so production domains are not code changes.
4. **Add rate limiting** to api/v1 checkout, orders, and catalog (and optionally payments/inventory).

### Medium Refactors

1. **Unify or bridge order systems:** Either (a) create FinancialOrder from checkout (and lock when payment is initiated) and sync status from PaymentSucceeded (e.g. listener that finds or creates FinancialOrder by order_id and calls markPaid), or (b) deprecate one system and migrate. Document and implement a single path from “checkout completed + payment succeeded” to “invoice can be created.”
2. **Single Money VO:** Choose one (e.g. App\ValueObjects\Money with fromCents/fromMinorUnits) and use it everywhere; add adapter at module boundaries if needed.
3. **API auth:** Define storefront auth (e.g. optional API key + tenant, or session for same-origin). Add middleware to checkout/orders/payments and add tests.
4. **Plan limit enforcement:** Centralize in one service (e.g. PlanLimitEnforcer) and call from both Filament and API for product (and any other limited) creation.

### Critical Architecture Corrections

1. **Payment → FinancialOrder / Invoice:** Implement a consistent path: when payment succeeds (e.g. ConfirmPaymentHandler or PaymentSucceeded listener), either create/update FinancialOrder and fire OrderPaid(FinancialOrder) so that financial_transactions and (optional) invoice are created, or explicitly document that invoicing is Filament-only and disable auto-invoice for API orders.
2. **Financial order creation from checkout:** If product is to support “invoice for every paid order,” either create FinancialOrder (and lock) when order is created at checkout (with snapshot from cart), or create it when payment is confirmed and link by order_id (or external_id). Ensure OrderLockService (and optionally OrderCurrencySnapshotService) is invoked in that path.
3. **Payment table currency snapshot:** When recording payment (e.g. in Payments module or in a new listener), if payment currency differs from order currency, call CurrencyConversionService::convertWithSnapshot and store payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base on payments table (or equivalent).

---

## 11. Production Readiness Score

| Dimension | Score (0–10) | Notes |
|-----------|--------------|--------|
| **Architecture** | 6 | Clear modules and DDD in places; dual order system and no checkout→financial path hurt. Two Money VOs. |
| **Financial Integrity** | 5 | Money as integer and snapshot design are good; currency snapshot not filled on lock; payment/invoice decoupled from API checkout. |
| **Multi-Tenant Safety** | 8 | DB-per-tenant, scopes, feature limits. Exchange rates global (document). |
| **Security** | 4 | RBAC and policies good in Filament. API v1 and landlord billing unauthenticated; no rate limits on critical paths. |
| **Performance** | 6 | Indexes present; some N+1 and cache opportunities; movement table growth. |
| **Test Coverage** | 5 | Good feature tests for many areas; suite broken by Filament; critical checkout→financial path untested. |

**Overall Production Readiness Score: 34 / 60 → ~57/100** (weighted average of dimensions, normalized to 100).

**Verdict:** Not production-ready for high GMV without addressing the **critical** items: order/payment/invoice alignment, API auth, and currency snapshot on lock. Filament fixes and test suite stability are also required for confidence.

---

## 12. Recommended Roadmap

### Phase 1 — Critical Fixes (Blockers)

1. **Define and implement order/payment/invoice flow:** Decide whether every paid order (from API) must have a FinancialOrder and optional invoice. If yes: (a) add listener to PaymentSucceeded (or ConfirmPaymentHandler) that creates or finds FinancialOrder by order_id (or link table), locks it (OrderLockService), marks it paid (OrderPaymentService), so OrderPaid (Financial) fires and CreateInvoiceOnOrderPaidListener runs; or (b) create FinancialOrder at checkout and update it on payment success. Ensure OrderLockService (and OrderCurrencySnapshotService if multi-currency) is in the path.
2. **Secure API v1:** Add auth (e.g. Sanctum API token or optional bearer) to checkout, orders, payments, and optionally catalog/inventory; document intended client (storefront). Add tests for unauthenticated rejection and authenticated success.
3. **Fix Filament type errors** so that the full test suite runs (Landlord and Tenant panels). Resolve $navigationGroup / $navigationIcon and any Schema/Infolist signatures for Filament 3.
4. **Call OrderCurrencySnapshotService::fillSnapshot** from OrderLockService after locking (when base_currency/display_currency are needed), so multi-currency columns are populated for every locked FinancialOrder.

### Phase 2 — High Impact Improvements

1. **Unify Money usage:** Standardize on one Money VO (e.g. App\ValueObjects\Money) and use at boundaries; add fromMinorUnits if needed; refactor Modules to use it or add a thin adapter.
2. **Payment record currency snapshot:** When saving payment (tenant payments table), if multi-currency, compute and store payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base (or equivalent) using CurrencyConversionService::convertWithSnapshot.
3. **Rate limiting:** Apply throttle to api/v1 checkout, orders, catalog, and payments (and optionally inventory). Add tests.
4. **Landlord API auth:** Protect api/landlord/billing (except webhook and success/cancel/portal return) with API token or internal-only network.
5. **Central domains from env:** Move central_domains to CENTRAL_DOMAINS env; document and deploy.

### Phase 3 — Scaling Improvements

1. **Audit and movement retention:** Add prune or archive strategy for inventory_movements (and optionally audit logs) with retention policy and command.
2. **Cache tenant settings and feature flags** where read-heavy (e.g. tenant_currency_settings, plan features).
3. **Eager loading audit:** Review Filament list and detail pages for N+1; add with() where needed.
4. **Queue and Horizon:** Document and configure Horizon (or equivalent) for production; ensure LogAuditEntry and other ShouldQueue listeners use a dedicated queue and workers.

### Phase 4 — Enterprise Enhancements

1. **Promotions engine:** Implement rule application at checkout (e.g. first_purchase, usage_limit_per_customer, customer_email) using CustomerPromotionEligibilityService and apply discounts to cart/order.
2. **API versioning contract:** Document /api/v1 as mandatory; add header or path contract for future v2.
3. **Read replicas / central DB scaling:** If central DB becomes a bottleneck, introduce read replicas for plan/feature/subscription reads.
4. **Event stream for analytics:** Optionally publish high-value events (order placed, subscription changed) to a bus or queue for external analytics and reporting.

---

*End of audit. All conclusions are from codebase analysis only; no code was modified.*
