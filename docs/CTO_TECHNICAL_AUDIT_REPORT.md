# SaaS E-Commerce — CTO Technical Audit Report

**Date:** February 27, 2025  
**Scope:** Full repository scan; audit-only (no code modified).

---

## 1) Executive Summary

The project is a **Laravel 12 multi-tenant SaaS e-commerce** application with **database-per-tenant** (stancl/tenancy), **modular domain logic** (Cart, Catalog, Checkout, Orders, Payments, Financial, Inventory, Shared), **Filament 5** (Landlord + Tenant panels), and a **financial pipeline** (operational order → FinancialOrder → lock → payment → invoice → ledger). Architecture is coherent; financial design (Money VO, snapshot hashing, immutability guards, idempotency, reconciliation job) is strong. **Risks:** Landlord Filament panel is not restricted to central domains; `/api/user` has no tenant middleware (acceptable if used only on central); Stripe webhook has no CSRF exemption (correct for webhooks); `.env.example` typo `sass-ecommerce.test`; and `config('tenancy.database.central_connection', 'central')` can resolve to a non-existent `central` connection if `DB_CONNECTION` is unset. **Verdict:** Production-ready after addressing Landlord panel domain restriction, config/typo details, and confirming full test suite (including Filament) passes.

---

## 2) Architecture Strengths

- **Modular structure:** `app/Modules/*` (Cart, Catalog, Checkout, Orders, Payments, Inventory, Financial, Shared) with Application/Infrastructure/Domain (or equivalent) and clear boundaries. Checkout orchestrates cart, order, payment, inventory, promotions via contracts.
- **Dependency direction:** Modules depend on Shared (Money, exceptions, audit); app-level `Services` (Financial, Invoice, Ledger, Currency, Reporting) sit above modules and are used by listeners and Filament.
- **Business logic placement:** Domain logic in Modules (handlers, entities, value objects); orchestration in `CheckoutOrchestrator` and `BillingService`; financial lifecycle in `OrderLockService`, `OrderPaymentService`, `RefundService`, `FinancialOrderSyncService`, `InvoiceService`, `LedgerService`.
- **Financial logic:** Isolated in `app/Services/Financial` and `app/Modules/Financial`; listeners (SyncFinancialOrderOnPaymentSucceeded, CreateInvoiceOnOrderPaid, CreateLedgerTransactionOnOrderPaid, CreateLedgerReversalOnOrderRefunded, CreateFinancialTransactionListener) drive the pipeline from `PaymentSucceeded` / `OrderPaid` / `OrderRefunded`.
- **Tenancy:** Handled by stancl/tenancy (InitializeTenancyBySubdomain, PreventAccessFromCentralDomains) on all tenant API routes and Tenant Filament panel; Livewire update route and FilePreviewController use tenant middleware in `TenancyServiceProvider`.
- **Observers:** Used for audit (Plan, Feature, Subscription, Tenant, User, ProductModel, OrderModel, StockItemModel); no observers on core financial models (changes go through services).
- **Routes:** Clear split: `routes/web.php` (welcome, health), `routes/api.php` (includes landlord + v1), `routes/tenant.php` (web, invoice PDF), `routes/console.php` (schedule). Tenant v1 routes each apply `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`.

---

## 3) Architecture Weaknesses

- **Landlord panel domain restriction:** Landlord Filament panel (`path('admin')`) has no middleware to restrict access to central domains. If a user visits `tenant-subdomain.example.com/admin`, the landlord panel can load; `EnsureSuperAdmin` then blocks non–super-admins. **Recommendation:** Add middleware that allows only central domains (e.g. check host against `config('tenancy.central_domains')`) and returns 404 or redirects on tenant domains.
- **Cross-module coupling:** Checkout module uses `OrderModel` and `InventoryAllocationService` (app Services); promotion logic is in app Services and called from Checkout. Acceptable but creates a dependency from Module → app layer.
- **Layer consistency:** Some “Application” services live in `app/Services` (Financial, Invoice, Ledger) and some in `app/Modules/*/Application`. Not a violation but mixed style.
- **API route `/api/user`:** No tenant middleware; it uses default DB (central when tenancy not initialized). Correct only if this route is used solely for central/landlord API; document that and ensure it is never called with tenant context if users live in tenant DB elsewhere.

---

## 4) Package Configuration Issues

- **stancl/tenancy:**
  - `config/tenancy.php` present; bootstrappers: Database, Cache, Filesystem, Queue, Redis.
  - `central_connection` = `env('DB_CONNECTION', 'central')`. If `DB_CONNECTION` is not set, Laravel default is `sqlite` from `config/database.php`, but tenancy would use `'central'`; there is **no** `'central'` key in `config/database.php` connections. **Risk:** With no `DB_CONNECTION` in env, central connection could be invalid. Set `DB_CONNECTION` explicitly (e.g. `sqlite` or `mysql`) so tenancy uses an existing connection.
  - `.env.example` and default in tenancy use `sass-ecommerce.test` (typo for “saas”). Fix to `saas-ecommerce.test` or match actual host.

- **spatie/laravel-permission:** Configured in `config/permission.php`; used for tenant roles/permissions and Landlord (e.g. `LandlordRole`). No issues found; tables are tenant-scoped via DB connection.

- **laravel/sanctum:**
  - `config/sanctum.php`: `guard` => `['web', 'customer']`; stateful domains from env.
  - `config/auth.php`: no `sanctum` guard; `web` and `customer` (driver sanctum, provider customers). API uses `auth:sanctum` (token/session) and `auth:customer` for customer API. Correct.

- **filament/filament (v5):** No `config/filament.php` in repo (Filament 5 may rely on defaults/published config). Tenant panel uses `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`; Landlord uses `EnsureSuperAdmin` only. Resources use `Filament\Schemas\Schema` and `Filament\Tables\Table`; confirm these are the correct Filament 5 APIs (vs deprecated v2/v3 namespaces).

- **Queue:** `config/queue.php`: default `env('QUEUE_CONNECTION', 'database')`; Redis connection config present. Horizon defines supervisors for `default`, `audit`, `financial`, `billing`, `low`. **Risk:** If production runs with `QUEUE_CONNECTION=database` but Horizon expects Redis, Horizon will not process jobs. Docs state production should use `QUEUE_CONNECTION=redis`.

- **Cache:** Default `env('CACHE_STORE', 'database')`. Tenancy uses CacheTenancyBootstrapper (tag-based). With Redis, tag support is required; `redis` driver supports tags. No issue if production uses Redis; with multiple app nodes, avoid `file` cache.

- **Logging:** `security` channel exists (daily, `storage/logs/security.log`). FinancialOrder immutability violations log to `Log::channel('security')`. Ensure `security` is not included in a broad `LOG_STACK` that sends to external systems without access control.

- **Stripe:** `config/services.php`: `stripe.key`, `stripe.secret`, `stripe.webhook_secret` from env. Webhook controller validates signature; idempotency via cache + `StripeEvent` model. Webhook route uses `throttle:webhook` (120/min by IP); no CSRF on POST (correct for webhooks).

- **Broadcasting:** `BROADCAST_CONNECTION=log` in `.env.example`. No real-time features audited; no misconfiguration detected.

---

## 5) Multi-Tenancy Risks

- **Initialization:** Tenancy is initialized by **subdomain** on API v1 routes and Tenant Filament (InitializeTenancyBySubdomain + PreventAccessFromCentralDomains). Central domains list from `config('tenancy.central_domains')` (env `CENTRAL_DOMAINS`). Safe for `config:cache` (env read at runtime).
- **Database per tenant:** DatabaseTenancyBootstrapper switches connection; tenant DBs created with prefix `tenant` + id. Migrations in `database/migrations/tenant`. No central models written to tenant DB in the code paths checked.
- **Cache/queue/filesystem:** CacheTenancyBootstrapper, QueueTenancyBootstrapper, FilesystemTenancyBootstrapper configured. `tenant_cache_key()` helper used for idempotency in landlord/out-of-request context (e.g. CreateLedgerReversalOnOrderRefundedListener) to avoid cross-tenant key collision.
- **tenant_id usage:** FinancialOrder, Invoice, Refund, Ledger, and other tenant-scoped models use `tenant_id` and/or `scopeForTenant`. ReconcileFinancialDataJob iterates tenants and runs `tenancy()->initialize($tenant)` before `reconcile()`; reconciliation is correctly scoped.
- **Risks:**
  1. Landlord panel not restricted to central domains (see §3).
  2. If `DB_CONNECTION` is unset, `central_connection` could be `'central'` and missing in `database.php`.
  3. `routes/tenant.php` is loaded without explicit tenant middleware in the route file; it is loaded inside `TenancyServiceProvider::mapRoutes()` which runs on boot. Stancl UniversalRoutes feature is used; tenant routes are typically reached only after tenancy is initialized by the route group that mounts them. Confirm in your routing setup that tenant routes (e.g. invoice PDF) are only registered or reachable when tenancy is already initialized (e.g. via a tenant route group); otherwise add tenant initialization middleware to that group.

---

## 6) Financial Integrity Status

- **Flow traced:** Cart → Checkout (CheckoutOrchestrator) → Order (OrderModel) + Payment (PaymentService) → ConfirmPayment → `PaymentSucceeded` → SyncFinancialOrderOnPaymentSucceededListener → FinancialOrder sync → lock (OrderLockService) → markPaid (OrderPaymentService) → `OrderPaid` → CreateInvoiceOnOrderPaidListener (if enabled), CreateLedgerTransactionOnOrderPaidListener, CreateFinancialTransactionListener. Refund: RefundService (validates refundable amount, creates Refund + FinancialTransaction, dispatches `OrderRefunded`) → CreateLedgerReversalOnOrderRefundedListener, CreateFinancialTransactionListener (idempotent).
- **Money:** `App\Modules\Shared\Domain\ValueObjects\Money` — minor units (int), no float; currency enforced; used in checkout and payment.
- **Snapshot/hashing:** OrderLockService builds `snapshot` and calls `setSnapshotHashFromCurrentState()`. `SnapshotHash::hash()` (SHA-256 of sorted JSON). FinancialOrder has `verifySnapshotIntegrity()` and immutability in `booted()` (LOCKED_ATTRIBUTES); throws `FinancialOrderLockedException` and reverts dirty attributes.
- **Immutability:** FinancialOrder locked after lock; Invoice locked after issuance (logic in codebase). No recalculation after lock; invoice uses order snapshot.
- **Idempotency:** SyncFinancialOrderOnPaymentSucceededListener: cache key `payment_confirmed:{paymentId}` (TTL 86400). CreateLedgerReversalOnOrderRefundedListener: `tenant_cache_key('refund_ledger:...')`. CreateFinancialTransactionListener and CreateLedgerTransactionOnOrderPaidListener check existing records before creating.
- **Ledger:** LedgerService creates balanced transactions (debits = credits); CreateLedgerTransactionOnOrderPaidListener and CreateLedgerReversalOnOrderRefundedListener use CASH, REV, TAX (and reversal entries). FinancialReconciliationService checks ledger balance, invoice total vs order total, and payments sum vs order total; logs only, does not auto-fix.
- **Reconciliation job:** ReconcileFinancialDataJob runs daily at 03:00; initializes each tenant and runs `FinancialReconciliationService::reconcile()`. Present and correctly scoped.
- **Float usage:** Not found in financial calculations; amounts in cents (int).
- **Direct inserts bypassing services:** Not observed; RefundService and listeners use services/models consistently.

---

## 7) Security Assessment

- **API authentication:** Tenant v1 write endpoints use `auth:sanctum` (staff) or `auth:customer` (customer). Catalog read (products index/show) is public; catalog write and other v1 routes require auth. Landlord API uses `auth:sanctum`. Tests (ApiAuthorizationTest) assert 401 for unauthenticated POST to checkout, orders, payments, inventory.
- **Sanctum guards:** `auth.php`: web (session), customer (sanctum, provider customers). Sanctum config `guard` includes `web` and `customer`. Staff tokens use `users` provider via Sanctum.
- **Rate limiting:** `checkout`, `api`, `payment`, `payment-confirm`, `webhook`, `customer-register`, `customer-login`, etc. defined in AppServiceProvider. Checkout limiter includes tenant in key when tenant() exists.
- **Landlord route protection:** Landlord API under `api/landlord`; plan/subscription/billing write routes use `auth:sanctum`; webhook is public with signature verification and idempotency.
- **Stripe webhook:** Signature verified with `STRIPE_WEBHOOK_SECRET`; invalid payload/signature return 400; processing errors return 500; no sensitive data in responses.
- **CSRF:** Web routes use VerifyCsrfToken (Filament, tenant web). API uses stateless auth (Sanctum); webhook is POST without CSRF (correct).
- **Mass assignment:** Models use `$fillable`; no `$guarded = []` observed on critical models.
- **Sensitive data in logs:** Logs use tenant_id, order_id, payment_id; no passwords or tokens. FinancialOrder immutability violations go to `security` channel. Ensure log aggregation does not expose security channel to inappropriate audiences.

---

## 8) Infrastructure Readiness

- **Horizon:** `config/horizon.php` present; supervisors for default, audit, financial, billing, low; production/local env overrides. Horizon middleware `['web']`; ensure Redis is used when running Horizon.
- **Queue separation:** Named queues (financial, audit, billing, low) defined; financial/audit have lower maxProcesses and tries to reduce duplicate processing risk.
- **Read/write DB:** `config/database.php` has read/write arrays for mysql/pgsql (DB_READ_HOST, DB_WRITE_HOST). Tenancy uses a single tenant connection; read/write split would need to be applied inside tenant connection config if desired.
- **Health:** `GET /health` (HealthController) checks DB, Redis (if cache or queue use Redis), and queue; returns 503 if any fail. `GET /up` (Laravel default) also registered. Health does not expose secrets.
- **Deployment:** `docs/DEPLOYMENT_CHECKLIST.md` references CENTRAL_DOMAINS, queue, cache, Stripe. `config:cache` and `route:cache` are safe: tenancy central_domains and other critical values read from env.
- **Env:** `.env.example` documents APP_*, DB_*, QUEUE_CONNECTION, CACHE_STORE, CENTRAL_DOMAINS, Stripe, SESSION_DRIVER, SYSTEM_READ_ONLY. Missing: AUTH_MODEL, AUTH_GUARD, SANCTUM_STATEFUL_DOMAINS (optional). `config/system.php` read_only from `SYSTEM_READ_ONLY`; EnsureSystemNotReadOnly prepended globally.
- **Hardcoded values:** Central domains default in tenancy and .env.example use `sass-ecommerce.test` (typo). No other dangerous hardcoding found.

---

## 9) Test Coverage Status

- **Present:** Unit (Money, LedgerService, PromotionEvaluationService), Feature (financial pipeline, PaymentSucceeded→FinancialOrder/Invoice/Ledger, checkout flow, refund overpayment, snapshot immutability, tax/order calculation, reconciliation, tenant isolation, API auth, rate limit, Stripe webhook, Filament Landlord/Tenant panels, multi-currency, multi-location inventory, invoice lifecycle, customer identity, plan limits, audit, idempotent financial job).
- **Financial:** FullFinancialIntegrityTest, FullFinancialPipelineTest, PaymentSucceededFinancialPipelineTest, CheckoutToInvoiceFlowTest, RefundOverpaymentTest, SnapshotImmutabilityTest, FinancialReconciliationTest, FinancialImmutabilityTest, FinancialListenerFailureSurfacesTest, IdempotentFinancialJobTest.
- **Tenant isolation:** TenantIsolationTest (financial orders and product data isolated per tenant).
- **API auth:** ApiAuthorizationTest (401 for unauthenticated POST to checkout, orders, payments, inventory; authenticated tenant user can reach protected endpoint).
- **Risks:** Tests that boot Filament (e.g. TenantPanelTest, LandlordPanelTest) can be slow or fragile; Filament 5 upgrade may require test updates. No dedicated test found that asserts Landlord panel is inaccessible on tenant host (would require domain restriction middleware first). Reconciliation job is covered via FinancialReconciliationTest and ReconcileFinancialDataJob usage.

---

## 10) Critical Risk List (Ordered by Severity)

1. **Landlord panel on tenant domain:** Landlord Filament is reachable at `/admin` on any host; only authorization (EnsureSuperAdmin) limits access. **Mitigation:** Restrict Landlord panel to central domains (middleware that checks host against `tenancy.central_domains`).
2. **Central DB connection name:** `tenancy.database.central_connection` defaults to `'central'` when `DB_CONNECTION` is unset; `database.php` has no `central` connection. **Mitigation:** Set `DB_CONNECTION` in env (e.g. `sqlite` or `mysql`) or add a `central` connection that points to the same DB.
3. **Production queue driver:** If production runs with `QUEUE_CONNECTION=database` while Horizon is configured for Redis, jobs will not be processed by Horizon. **Mitigation:** Use `QUEUE_CONNECTION=redis` in production and run Horizon.
4. **Typo in central domains:** `.env.example` and tenancy default use `sass-ecommerce.test`. **Mitigation:** Replace with `saas-ecommerce.test` or the real central domain.
5. **Stripe webhook secret:** Empty `STRIPE_WEBHOOK_SECRET` causes webhook to return 500 and log a warning; no silent failure. **Mitigation:** Set in production and verify endpoint.
6. **Tenant route registration:** Confirm that routes in `tenant.php` (e.g. invoice PDF) are only served when tenancy is initialized (e.g. via route group with tenant middleware); otherwise add tenant initialization to that group.

---

## 11) Recommended Next Actions (Minimal, Realistic)

1. **Landlord panel:** Add middleware to the Landlord Filament panel that allows only hosts in `config('tenancy.central_domains')`; return 404 or redirect for others.
2. **Config/env:** Ensure `DB_CONNECTION` is set in all environments; fix `sass-ecommerce` → `saas-ecommerce` in `.env.example` and tenancy default.
3. **Production checklist:** Set `QUEUE_CONNECTION=redis`, run Horizon; set `STRIPE_WEBHOOK_SECRET`; set `CENTRAL_DOMAINS` to production central hosts; run `php artisan config:cache` and `route:cache` after deployment.
4. **Tests:** Run full suite (`php artisan test`); fix any Filament or env-dependent failures; add a smoke test for checkout → payment → invoice → ledger if not already covered by existing pipeline tests.
5. **Filament 5:** Confirm all Filament resources and tables use current Filament 5 APIs (Schema, Table, etc.) and fix deprecations if any.
6. **Documentation:** Document that `/api/user` is for central/landlord API only and must not be used from tenant storefronts if user data is tenant-specific elsewhere.

---

*Audit scope: Codebase only; no code was modified. Findings are based on actual files and config; anything not found is explicitly marked as such in the sections above.*
