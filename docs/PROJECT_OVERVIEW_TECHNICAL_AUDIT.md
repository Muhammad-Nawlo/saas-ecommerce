# Complete Project Overview — Technical Audit

**Audit type:** Deep technical analysis — no code modified.  
**Scope:** Entire repository.  
**Perspective:** CTO / acquisition due diligence.

---

## Executive Summary

The codebase is a **modular monolith** with **DDD-inspired** layering in core modules (Catalog, Cart, Orders, Payments, Checkout, Inventory). Financial and billing concerns live partly in `app/` (Services, Models, Listeners) and partly in `app/Modules/Financial`. **Operational order** (OrderModel) and **Financial order** (FinancialOrder) are deliberately split; the bridge is event-driven (PaymentSucceeded → sync, lock, mark paid → OrderPaid → invoice, ledger). Money is centralized (Shared Money VO, minor units only). Multi-tenancy is **database-per-tenant** (Stancl); cache, queue, and filesystem are tenant-aware. Post-audit hardening (Phases E & F) added snapshot hashing, immutability logging, structured audit, tenant cache key helper, read/write DB readiness, queue idempotency, instrumentation hooks, and enterprise docs.

**Critical gaps:** No vendor/wallet (B2B SaaS only). Catalog product **read** is public (storefront); write paths and checkout/orders/payments are behind **auth:sanctum**. Landlord plans **index** is public; subscribe/cancel/billing checkout are protected. EventBus is correctly bound; if it were null, financial pipeline would silently not run. Test suite can be blocked by Filament type issues. No automated reconciliation job; integrity check command exists but is opt-in.

**Bottom line:** Strong financial design and tenant isolation; suitable for investor presentation after addressing API/auth clarity, test suite stability, and runbook/reconciliation visibility.

---

## SECTION 1 — HIGH LEVEL ARCHITECTURE

### 1.1 Architecture style

| Style | Present | Notes |
|-------|---------|--------|
| **Modular monolith** | Yes | Single deployable; clear module boundaries in `app/Modules/*`. |
| **DDD-inspired** | Yes | Application (Commands, Handlers), Domain (Entities, Events, ValueObjects), Infrastructure (Persistence, Gateways) inside Catalog, Cart, Orders, Payments, Checkout, Inventory. |
| **Layered** | Partial | Shared and Landlord are more service/layer than full DDD; Financial/Invoice/Ledger in `app/Services`, `app/Models`, `app/Listeners`. |
| **Hybrid** | Yes | Modules under `app/Modules` are DDD-ish; app-level Services/Models/Listeners form a second “layer” for financial, invoice, ledger, promotion, reporting. |

### 1.2 Main modules

| Module | Purpose | Main domain entities | Main services | Events | External deps |
|--------|---------|----------------------|---------------|--------|---------------|
| **Shared** | Cross-cutting: Money, TenantId, domain exceptions, EventBus, TransactionManager, Audit (AuditLogger, SnapshotHash). | — | — | — | Laravel, Stancl (tenant id). |
| **Catalog** | Products (CRUD), categories; product listing public, writes gated. | Product | CreateProductHandler, UpdateProductPriceHandler | ProductPriceChanged | FeatureResolver (products_limit). |
| **Cart** | Cart + items; convert to order. | Cart, CartItem | AddItemToCartHandler, CartOrderCreationService / OrderCreationService | — | Orders (OrderService), Money. |
| **Orders** | Operational order lifecycle (create, confirm, pay, ship, cancel). | Order, OrderItem | CreateOrderHandler, ConfirmOrderHandler, etc. | OrderCreated, OrderPaid (Orders) | Payments, Inventory. |
| **Checkout** | Orchestrator: cart → order → promotion → payment intent; inventory reserve/allocate. | — | CheckoutOrchestrator, CheckoutCartService, CheckoutOrderService, CheckoutPaymentService, CheckoutInventoryService | — | Cart, Orders, Payments, Inventory, Promotion. |
| **Payments** | Create/confirm payment; Stripe gateway; domain event on success. | Payment | PaymentService, ConfirmPaymentHandler, CreatePaymentHandler | PaymentSucceeded, PaymentCreated, etc. | Stripe SDK. |
| **Inventory** | Stock (single + multi-location), reservations, movements, transfers. | — | InventoryAllocationService, InventoryLocationService, etc. | — | FeatureResolver (multi_location_inventory). |
| **Financial** (app-level) | FinancialOrder sync, lock, mark paid; tax; snapshot; OrderPaid/OrderLocked/OrderRefunded. | FinancialOrder (model) | OrderLockService, OrderPaymentService, FinancialOrderSyncService, TaxCalculator, PaymentSnapshotService, OrderCurrencySnapshotService | OrderLocked, OrderPaid, OrderRefunded | Orders (OrderModel), Ledger, Currency. |
| **Invoice** (app-level) | Invoice from FinancialOrder; issue; apply payment; credit notes; PDF. | Invoice, InvoiceItem (models) | InvoiceService, InvoiceNumberGenerator | — | FinancialOrder. |
| **Ledger** (app-level) | Double-entry: ledgers, accounts, transactions, entries; balanced only. | LedgerTransaction, LedgerEntry (models) | LedgerService | — | FinancialOrder. |
| **Refund** (app-level) | Validate refundable amount; Refund record; FinancialTransaction REFUND; OrderRefunded. | Refund (model) | RefundService | OrderRefunded | FinancialOrder, Ledger (listeners). |
| **Landlord SaaS Billing** | Tenants, plans, features, subscriptions; Stripe checkout/webhook/portal; feature resolution. | Plan, Feature, Subscription (Landlord models) | BillingService, FeatureResolver, BillingAnalyticsService, FeatureUsageService | SubscriptionCreated, etc. | Stripe API. |
| **RBAC** | Spatie permissions; Landlord + Tenant roles/permissions; policies (Filament). | — | — | — | Spatie. |
| **Audit** | Tenant + landlord audit logs; observers; LogAuditEntry job; structured log (tenant_id, actor_id, entity_type, event_type, before/after_state). | — | AuditLogger | — | Queue (low/audit). |
| **Vendor Wallet** | **Does not exist.** No vendor/seller, no wallet, no commission/payout. | — | — | — | — |

---

## SECTION 2 — FOLDER STRUCTURE ANALYSIS

### Map

- **app/** — Controllers (Health, Api), Jobs (LogAuditEntry), Events (Financial), Listeners (Financial, Invoice, Order), Models (Financial, Invoice, Ledger, Refund, Promotion, Currency, Customer, etc.), Observers, Policies, Providers, Services (Financial, Invoice, Ledger, Currency, Promotion, Reporting, Tenant), Support (Instrumentation), Http/Middleware, Console/Commands, Contracts (TenantDatabaseResolver), Helpers (tenant_features, tenant_cache).
- **app/Modules/** — Catalog, Cart, Checkout, Orders, Payments, Inventory (each with Application, Domain, Infrastructure; some with Http/Api, Providers). Shared: Domain (ValueObjects, Exceptions, Contracts), Infrastructure (Audit, Messaging, Persistence).
- **app/ValueObjects/** — Not present; canonical value objects live in `app/Modules/Shared/Domain/ValueObjects` (Money, TenantId, etc.).
- **app/Services/** — Feature-based (Financial, Invoice, Ledger, Currency, Promotion, Reporting, Inventory, Tenant). Cross-module and non-module.
- **routes/** — web, api (v1: catalog, cart, checkout, orders, payments, inventory, customer, reports; landlord: billing), tenant, console.
- **config/** — Standard Laravel + audit, tenancy, retention, currency, horizon, system (read_only).

### Isolation and violations

- **Module isolation:** Respected inside `app/Modules/*`: Domain has no infrastructure imports; Application uses repositories and domain types; Infrastructure implements interfaces. **Violation:** FinancialOrderSyncService (app) depends on `OrderModel` (Modules\Orders\Infrastructure) — app layer pulls from a module’s infrastructure. Acceptable as a single bridge; not ideal.
- **Cross-module:** Checkout → Cart, Orders, Payments, Inventory, Promotion. No circular dependency between these. Shared is used by all; no module depends on another module’s Application by contract only (some depend on concrete handlers/services).
- **Circular dependencies:** None identified at package/class level.
- **Domain leakage:** Eloquent models (FinancialOrder, Invoice, PaymentModel) live in app/Models or Modules/Infrastructure; domain entities (Order, Payment, Cart, Product) live in Modules/Domain. “Order” is overloaded: operational Order (Modules\Orders) vs FinancialOrder (app\Models\Financial). Documented and intentional; still a cognitive load.

---

## SECTION 3 — FINANCIAL ARCHITECTURE

### Operational Order vs FinancialOrder

- **Operational:** `OrderModel` + `order_items`; cart/checkout and fulfillment; mutable until closed.
- **Financial:** `FinancialOrder` + items + tax lines; created/synced from operational order on payment success; locked (tax + snapshot + snapshot_hash); then mark paid. Invoicing and ledger use FinancialOrder only.

### Order locking, snapshot, money, payment snapshot, invoice, ledger, refund

- **Lock:** OrderLockService locks draft FinancialOrder: tax calculation, totals, snapshot (items, tax_lines, applied_promotions), currency snapshot, **snapshot_hash** (SHA-256), then status=pending, locked_at set.
- **Money:** Single `Money` VO (Shared); minor units only; currency-strict add/subtract.
- **Payment snapshot:** PaymentSnapshotService fills payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base on confirm; PaymentModel sets **snapshot_hash** on first save when status=succeeded.
- **Invoice:** Created from locked FinancialOrder snapshot; issued → status=issued, locked_at, **snapshot_hash**; immutability guard on totals/snapshot.
- **Ledger:** LedgerService creates balanced transactions only (debits = credits); OrderPaid → debit CASH, credit REV/TAX; OrderRefunded → reversing entries.
- **Refund:** RefundService validates refundable amount, creates Refund + FinancialTransaction TYPE_REFUND, sets order status REFUNDED, dispatches OrderRefunded; listener creates ledger reversal; idempotency key for refund ledger.

### Immutability and snapshot hash

- **FinancialOrder:** After lock, updates to locked attributes throw FinancialOrderLockedException; security log; no DB change.
- **Invoice:** After issue, locked attributes throw InvoiceLockedException; security log.
- **Payment:** After status=succeeded, locked attributes throw PaymentConfirmedException; security log.
- **Snapshot hash:** Implemented on FinancialOrder, Invoice, PaymentModel (set at lock/issue/confirm); `verifySnapshotIntegrity()` recomputes and logs to security channel on mismatch; no auto-correction.

### Assessment

| Item | Status |
|------|--------|
| Single Money VO, minor units | ✔ Correct |
| Operational vs Financial split, event bridge | ✔ Correct |
| Lock process, snapshot, currency snapshot | ✔ Correct |
| Payment snapshot + hash at confirm | ✔ Correct |
| Invoice from snapshot, issue, hash | ✔ Correct |
| Ledger balanced, immutable entries | ✔ Correct |
| Refund validation, ledger reversal, idempotency | ✔ Correct |
| Immutability guards + security log | ✔ Correct |
| Snapshot hash (tamper detection) | ✔ Implemented |
| EventBus null → no PaymentSucceeded | ⚠ Risky (binding verified in AppServiceProvider; env/DI must ensure it) |
| Proportional refund rounding | ⚠ Minor skew possible (acceptable) |
| Automated reconciliation job | ❌ Missing (integrity-check command exists; not scheduled) |
| Contract test: invoice always for paid order | ⚠ Covered by flow tests; suite may be blocked by Filament |

---

## SECTION 4 — MULTI-TENANCY

| Aspect | Implementation |
|--------|----------------|
| **Package** | Stancl Tenancy v3. |
| **Resolution** | Subdomain (API: InitializeTenancyBySubdomain); domain (Filament tenant); central_domains from env (CENTRAL_DOMAINS). |
| **Isolation** | Database-per-tenant (prefix+suffix). Central DB: users, tenants, domains, plans, features, subscriptions, stripe_events, landlord_audit_logs. Tenant DBs: all ecommerce, financial, invoice, ledger, etc. |
| **Cache** | CacheTenancyBootstrapper (tag/prefix when tenant initialized). Helper `tenant_cache_key($key, $tenantId)` for landlord-context or shared Redis. |
| **Queue** | QueueTenancyBootstrapper (tenant context for jobs). |
| **Filesystem** | FilesystemTenancyBootstrapper (tenant suffix on disks). |

**Isolation:** ✔ Tenant data in separate DBs; tenant_id used where applicable; scopes (e.g. forTenant) used. **Potential leak:** If tenant resolution is wrong (e.g. subdomain misconfiguration) or a bug passes another tenant ID into a query without scope, data could leak. Code consistently uses tenant('id') or model scopes; no raw tenant_id from request body on API.

---

## SECTION 5 — SECURITY STATUS

| Area | Status | Notes |
|------|--------|-------|
| **API auth (tenant v1)** | ✔ | Checkout, orders, payments, cart, inventory, reports, catalog writes: `auth:sanctum`. Customer: `auth:customer` for me, profile, addresses, export, delete. |
| **Catalog read** | ⚠ | GET products/list and detail are **public** (intentional for storefront). |
| **Sanctum / guards** | ✔ | web (session), customer (Sanctum, provider customers). API token for dashboard/manager. |
| **Rate limiting** | ✔ | checkout, payment-confirm, api, webhook, login, customer-register/login/forgot/reset. |
| **EventBus binding** | ✔ | EventBus bound to LaravelEventBus in AppServiceProvider. |
| **Feature/limit enforcement** | ✔ | FeatureResolver; products_limit, multi_currency, multi_location_inventory enforced in handlers and services; 403 on limit/feature. |
| **RBAC** | ✔ | Spatie; policies on Filament resources; Landlord and Tenant roles/permissions. |
| **Landlord API** | ⚠ | Plans index (GET) public; subscribe, cancel, billing checkout protected with auth:sanctum. Webhook throttled, signature-verified. |
| **Read-only mode** | ✔ | SYSTEM_READ_ONLY blocks POST/PUT/PATCH/DELETE (503). |
| **Security log channel** | ✔ | Dedicated channel for snapshot mismatch, immutability violations. |

**Summary:** Secure for intended use. Catalog read public by design. Landlord GET plans public (low risk). Ensure production uses HTTPS and correct CENTRAL_DOMAINS.

---

## SECTION 6 — TEST COVERAGE STATUS

| Area | Status | Notes |
|------|--------|-------|
| **Unit** | Strong | MoneyTest, LedgerServiceTest (balanced/unbalanced), PromotionEvaluationServiceTest. |
| **Feature** | Broad | Checkout, checkout→invoice, financial integrity, idempotency, immutability, refund, invoice (snapshot, balance, credit note), multi-currency, multi-location inventory, customer (register, login, rate limit, guest link, deletion), tenant isolation, RBAC, audit, Stripe webhook, plan limits. |
| **Financial integration** | Strong | FullFinancialIntegrityTest, CheckoutToInvoiceFlowTest, PaymentSucceededFinancialPipelineTest, RefundOverpaymentTest, SnapshotImmutabilityTest, TaxCalculationTest, OrderCalculationTest, IdempotentFinancialJobTest. |
| **Reconciliation** | Partial | FinancialReconciliationService exists; ReconcileFinancialDataJob; no dedicated test that asserts ledger vs financial_transactions in CI. Integrity check command tested implicitly. |
| **API auth** | Present | ApiAuthorizationTest, RateLimiterTest. Catalog read is public by design. |
| **Tenant isolation** | Present | TenantIsolationTest. |

**Strong:** Money, ledger balance, promotion evaluation, checkout→invoice→ledger flow, idempotency, immutability, multi-currency, multi-location, customer identity. **Missing/critical:** Full suite may not run if Filament bootstrap fails (e.g. Landlord resource type errors). No explicit “reconciliation job run and assert no mismatches” test. **Risk:** Filament-related fatals can hide regressions in financial or API tests when run together.

---

## SECTION 7 — INFRASTRUCTURE READINESS

| Item | Status |
|------|--------|
| **Redis** | Used for cache/queue/session when configured; health check includes Redis when cache or queue is redis. |
| **Horizon** | Configured (financial, default, billing, audit, low); financial higher priority, limited retries, timeout 90s. |
| **Queue separation** | financial, default, low, audit, billing; audit job uses config audit.queue. |
| **Config cache** | Documented (config:cache); tenancy central_domains uses env (safe for config:cache). |
| **Route cache** | Documented (route:cache); routes are non-closure. |
| **Health** | GET /health: database, redis (if cache/queue use redis), queue; 503 if any down. |
| **Deployment** | DEPLOYMENT_CHECKLIST.md: migrations, config/route cache, Horizon, Redis required for production. |
| **Logging** | Stack, single, daily, security channel; structured audit and instrumentation. |

**Readiness:** Production-ready with Redis + Horizon and checklist followed. Session/cache should be Redis for multi-node.

---

## SECTION 8 — PERFORMANCE & SCALABILITY

| Item | Status |
|------|--------|
| **Indexes** | tenant_id, status, order_id, (order_id, status), (tenant_id, status), (tenant_id, created_at) on financial_orders, payments, invoices; operational_order_id; ledger, promotions, inventory indexes present. |
| **N+1** | Mitigated in Filament (with(['items'], ['customer','order'], etc.); FinancialOrderResource with items; OrderResource with items. Not every list audited. |
| **Query hotspots** | Reporting uses aggregates and cache (5 min TTL). |
| **Queue** | Financial listeners run sync to preserve tenant context; audit/LogAuditEntry queued. Idempotency on payment confirm and refund ledger. |
| **Horizontal scaling** | Stateless app; session/cache/queue on Redis; read/write DB config; TenantDatabaseResolver interface for future sharding. |

**Verdict:** Adequate for current scale; indexes and cache in place; N+1 partially addressed; horizontal readiness documented and partially implemented.

---

## SECTION 9 — COMPLIANCE & AUDIT READINESS

| Item | Status |
|------|--------|
| **Audit log** | Tenant + landlord; structured (tenant_id, actor_id, entity_type, entity_id, event_type, before_state, after_state, timestamp); order lock/paid/refunded, invoice created/issued, payment confirmed, subscription plan/cancel/renew/failure. |
| **Financial immutability** | Enforced with domain exceptions and security log. |
| **Snapshot hashing** | FinancialOrder, Invoice, Payment; verifySnapshotIntegrity() on each. |
| **Data export** | TenantDataExportService (orders, financial_orders, invoices, payments, ledger_transactions) JSON bundle; no UI. |
| **Integrity check** | `php artisan system:integrity-check` (snapshot hashes, ledger balance, invoice vs financial order). |
| **Security log** | Dedicated channel for tamper and immutability events. |

**Verdict:** Audit trail and compliance hardening in place; export and integrity check available for due diligence.

---

## SECTION 10 — MATURITY SCORE

| Dimension | Score (0–100) | Rationale |
|-----------|----------------|-----------|
| **Architecture** | 78 | Clear modules and DDD in core; Shared single place for Money/exceptions; Financial in app/ with clear flow. Some app/module coupling (e.g. OrderModel in Financial sync). |
| **Financial integrity** | 88 | Money VO, snapshots, hashes, immutability, idempotency, balanced ledger, refund validation. Risk: EventBus null; no automated reconciliation run. |
| **Security** | 82 | API and landlord auth in place; rate limits; RBAC; security log. Catalog read public; landlord GET plans public. |
| **Scalability** | 72 | DB-per-tenant; Horizon and queue separation; read/write config; cache isolation; no sharding. |
| **Maintainability** | 78 | Handlers, services, events, docs; two “order” concepts and Filament type issues add onboarding cost. |
| **Investor readiness** | 80 | Strong financial and audit story; integrity command; export; enterprise and financial docs. Gaps: test suite stability, optional runbooks. |

**Overall (equal weight):** (78+88+82+72+78+80)/6 ≈ **79/100**.

---

## Top 10 Critical Risks (by severity)

| # | Risk | Severity | Description |
|---|------|----------|-------------|
| 1 | EventBus not bound or null in Payment repo | Critical | If EventBus is null when saving Payment, PaymentSucceeded never fires; no financial sync, invoice, or ledger. AppServiceProvider binds it; ensure no code path constructs repository without it. |
| 2 | Filament bootstrap fatal blocks full test suite | Critical | Landlord resource (e.g. AuditLogResource) type/usage can cause fatal; CI may skip or fail before financial/API tests run; regressions undetected. |
| 3 | No scheduled reconciliation | High | No cron/scheduler entry for `system:integrity-check` or ReconcileFinancialDataJob; integrity is opt-in; silent drift possible. |
| 4 | Two “order” concepts | High | Operational Order vs FinancialOrder is documented but easy to misuse; new code might update wrong aggregate or assume one source of truth. |
| 5 | Catalog product read public | Medium | GET products unauthenticated; acceptable for storefront; abuse (scraping, enumeration) possible without rate limit on that path. |
| 6 | Landlord plans index public | Medium | GET /api/landlord/plans unauthenticated; low sensitivity; still an information leak if plans are confidential. |
| 7 | N+1 not fully audited | Medium | New Filament resources or list pages could introduce N+1 under load. |
| 8 | Refund proportional rounding | Low | Large refunds could leave 1-cent skew in ledger; documented and acceptable. |
| 9 | Queue default database | Low | Production should use Redis + Horizon; default is database; deployment checklist and docs say Redis. |
| 10 | Vendor/wallet absent | Low | Not in scope; limits marketplace/commission use cases. |

---

## Module Overview Table

| Module | Location | Purpose | Key entities | Key services |
|--------|----------|---------|---------------|--------------|
| Shared | app/Modules/Shared | VO, exceptions, EventBus, audit, snapshot hash | Money, TenantId | AuditLogger, SnapshotHash |
| Catalog | app/Modules/Catalog | Products, categories | Product | CreateProductHandler, UpdateProductPriceHandler |
| Cart | app/Modules/Cart | Cart, items, convert to order | Cart, CartItem | AddItemToCartHandler, OrderCreationService |
| Orders | app/Modules/Orders | Operational order lifecycle | Order, OrderItem | CreateOrderHandler, ConfirmOrderHandler |
| Checkout | app/Modules/Checkout | Cart→order→payment intent, inventory | — | CheckoutOrchestrator, Checkout*Services |
| Payments | app/Modules/Payments | Create/confirm payment, Stripe | Payment | PaymentService, ConfirmPaymentHandler |
| Inventory | app/Modules/Inventory | Stock, locations, movements | — | InventoryAllocationService, etc. |
| Financial | app/Services/Financial, app/Models/Financial | Sync, lock, pay, tax, snapshot | FinancialOrder | OrderLockService, OrderPaymentService, FinancialOrderSyncService |
| Invoice | app/Services/Invoice, app/Models/Invoice | Invoice from order, issue, payments | Invoice | InvoiceService |
| Ledger | app/Services/Ledger, app/Models/Ledger | Double-entry | LedgerTransaction | LedgerService |
| Refund | app/Services/Financial/RefundService, app/Models/Refund | Refund flow | Refund | RefundService |
| Landlord Billing | app/Landlord | Tenants, plans, subscriptions, Stripe | Plan, Subscription | BillingService, FeatureResolver |
| Audit | app/Modules/Shared/Infrastructure/Audit, app/Jobs | Logs, structured audit | — | AuditLogger, LogAuditEntry |

---

## Risk Table (Severity)

| Severity | Count | Examples |
|----------|-------|----------|
| Critical | 2 | EventBus null; Filament test suite block |
| High | 2 | No scheduled reconciliation; dual order concepts |
| Medium | 4 | Catalog read public; landlord plans public; N+1; (others) |
| Low | 2 | Refund rounding; queue default; vendor absent |

---

## Strengths

- Single Money VO and integer minor units everywhere.
- Clear operational vs financial order split with event-driven bridge.
- Lock, snapshot, and snapshot_hash on FinancialOrder, Invoice, Payment; immutability enforced and logged.
- Idempotency on payment confirmation and refund ledger.
- Balanced ledger and refund reversal.
- Database-per-tenant and tenant-aware cache/queue/filesystem.
- API auth (Sanctum + customer guard) and rate limiting on sensitive routes.
- Feature/limit enforcement in services and API.
- Broad feature tests (checkout, financial, invoice, multi-currency, multi-location, customer).
- Health endpoint, deployment checklist, enterprise and financial docs.
- Integrity check command and TenantDataExportService for compliance.

---

## Weaknesses

- Financial and invoice/ledger logic largely in `app/` rather than under a single Financial module; coupling to OrderModel (Modules) from app.
- Test suite can be blocked by Filament bootstrap; no guaranteed CI run of full financial pipeline.
- No scheduled reconciliation or integrity check in default scheduler.
- Catalog read and landlord plans index public (by design or oversight).
- N+1 not audited everywhere; new list views may regress.
- Two “order” concepts require clear onboarding.

---

## Recommended Next Phase

1. **Stabilize tests:** Fix Filament Landlord resource types (or isolate financial/API tests so they run without Filament). Add a smoke test that runs checkout→payment→invoice→ledger and asserts integrity without full app bootstrap.
2. **Reconciliation visibility:** Schedule `system:integrity-check` or ReconcileFinancialDataJob (e.g. daily); alert on failures.
3. **Runbooks:** Document EventBus binding verification and recovery steps for payment/financial pipeline failures.
4. **Optional:** Add rate limit or auth for catalog product list if abuse is a concern; consider protecting landlord GET plans or documenting as intentional.

---

*End of Project Overview — Technical Audit. No code was modified.*
