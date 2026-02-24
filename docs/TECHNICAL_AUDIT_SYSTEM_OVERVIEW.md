# Full Technical Audit — SaaS E-Commerce Platform

**Audit date:** 2026-02-24  
**Scope:** Entire repository (Laravel 11, Stancl Tenancy, Filament, Stripe)  
**Method:** Codebase analysis only — no assumptions.

---

## 1. PROJECT CLASSIFICATION

### Tenancy model
- **Multi-tenant.** Single codebase serves many tenants (stores). No single-tenant or hybrid mode detected.
- **Database-per-tenant.** Each tenant has an isolated database (prefix `tenant` + tenant UUID). Central (landlord) database is separate. Configured in `config/tenancy.php` with SQLite/MySQL/PostgreSQL managers.

### Application architecture
- **Modular monolith.** One deployable application with clear module boundaries:
  - **app/Modules/** — tenant-facing domains (Catalog, Cart, Checkout, Inventory, Orders, Payments) with Application/Domain/Infrastructure layers.
  - **app/Landlord/** — central platform (tenants, plans, subscriptions, billing, Stripe).
- **Not microservices.** No separate services, no inter-service APIs or message brokers.
- **Not distributed.** Single app; queues are in-process (database/Redis), no event streaming.

### Architectural patterns (from code)
- **Repository:** Used in Catalog (ProductRepository), Cart (CartRepository), Orders (OrderRepository). Eloquent implementations in Infrastructure.
- **CQRS-style:** Commands (CreateProductCommand, CheckoutCartCommand, etc.) and Handlers; Command Bus (`LaravelCommandBus`) in Shared. No separate read models or event sourcing.
- **Service layer:** Checkout (CheckoutOrchestrator, CheckoutCartService, CheckoutPaymentService, etc.), Landlord (StripeService, FeatureResolver, TenantProvisioningService).
- **DTOs:** Landlord Billing (PlanDTO, SubscriptionDTO); modules return DTOs from handlers, converted to API Resources at HTTP boundary.
- **Observer pattern:** Model observers for audit (Product, Order, Inventory, User, Plan, Feature, Subscription, Tenant).
- **Policy-based authorization:** Gate policies for Product, Order, Customer, Inventory (tenant); Plan, Tenant, Subscription (landlord).

### Tenancy implementation
- **Package:** Stancl Tenancy v3 (`stancl/tenancy`).
- **Strategy:** Database-per-tenant (separate DB per tenant). Optional schema-per-tenant for PostgreSQL (commented in config).
- **Identification:**  
  - **API v1:** `InitializeTenancyBySubdomain` — tenant from subdomain.  
  - **Tenant Filament panel:** `InitializeTenancyByDomain` — tenant from domain.  
- **Central domains:** `127.0.0.1`, `localhost`, `sass-ecommerce.test` (no tenant).
- **Bootstrappers:** Database, Cache, Filesystem, Queue (tenant-aware).
- **Provisioning:** TenantCreated → CreateDatabase, MigrateDatabase; TenantDeleted → DeleteDatabase (`TenancyServiceProvider`).

---

## 2. FOLDER & DOMAIN STRUCTURE

### Full folder structure (relevant parts)

```
app/
├── Console/                    # PruneAuditLogsCommand, SuspendTenantsPastGracePeriodCommand
├── Constants/                  # LandlordPermissions, TenantPermissions
├── Enums/                     # LandlordRole, TenantRole
├── Filament/
│   ├── Landlord/              # Panel /admin — Resources, Widgets
│   └── Tenant/                # Panel /dashboard — Resources, Pages, Widgets
├── Helpers/                   # tenant_features.php (tenant_feature, tenant_limit)
├── Http/Middleware/           # Auth, tenancy, RBAC, idempotency
├── Jobs/                      # LogAuditEntry
├── Landlord/                  # Central platform
│   ├── Billing/               # Domain + Application + Infrastructure (plans, subscriptions, Stripe)
│   ├── Http/Controllers/     # StripeWebhookController, BillingCheckoutController
│   ├── Models/                # Tenant, Domain, Plan, Feature, PlanFeature, Subscription, StripeEvent, LandlordAuditLog
│   ├── Policies/
│   ├── Services/              # StripeService, FeatureResolver, TenantProvisioningService, etc.
│   ├── Events/                # TenantCreated, TenantDeleted, SubscriptionActivated
│   └── Listeners/             # CreateTenantDatabase, AssignDefaultPlan, etc.
├── Modules/
│   ├── Shared/                # Audit, Exceptions, Messaging (Command/Query/Event bus), ValueObjects
│   ├── Catalog/               # Products — Handlers, Repositories, HTTP API
│   ├── Cart/                  # Carts, items — Handlers, Repositories, HTTP API
│   ├── Checkout/              # Checkout flow — Orchestrator, Services, HTTP API
│   ├── Inventory/             # Stock — Commands/Handlers, HTTP API
│   ├── Orders/                # Orders — Commands, Repository, HTTP API
│   └── Payments/              # Payments — Commands, HTTP API
├── Models/                    # User (single model; used central + tenant by connection)
├── Observers/                 # Audit observers (Product, Order, Inventory, User, Plan, Feature, Subscription, Tenant)
├── Policies/                  # Tenant-scoped policies (Product, Order, Customer, Inventory)
├── Providers/                 # AppServiceProvider, EventServiceProvider, TenancyServiceProvider, Filament panels
config/                        # app, auth, audit, tenancy, permission, queue, etc.
database/
├── migrations/               # Central (users, tenants, domains, plans, subscriptions, permissions, audit, etc.)
├── migrations/tenant/         # Tenant (products, orders, carts, payments, stock, categories, permissions, audit)
├── seeders/
├── factories/
routes/
├── api.php                   # Prefix landlord + v1; includes api/landlord/billing.php, api/v1/*.php
├── api/landlord/billing.php
├── api/v1/                   # catalog, cart, checkout, orders, payments, inventory
├── web.php                   # GET /
├── console.php               # Scheduler, inspire
├── tenant.php                # Empty
resources/views/              # welcome, filament/tenant/pages/*
tests/Feature/, Unit/, Integration/
docs/                         # Deployment, tenancy, hardening, checklists
.cursor/rules/                # Project rules (tenancy, modules, permissions, etc.)
```

### Domain modules and bounded contexts

| Bounded context        | Location              | Responsibility                          | DB        |
|------------------------|----------------------|-----------------------------------------|-----------|
| Catalog                | app/Modules/Catalog   | Products (CRUD, activate/deactivate)     | Tenant    |
| Cart                   | app/Modules/Cart      | Cart + items, convert, abandon          | Tenant    |
| Checkout               | app/Modules/Checkout  | Checkout + confirm payment              | Tenant    |
| Inventory              | app/Modules/Inventory | Stock levels, reserve, release          | Tenant    |
| Orders                 | app/Modules/Orders    | Orders, items, confirm, pay, ship       | Tenant    |
| Payments               | app/Modules/Payments   | Payment create, confirm, refund         | Tenant    |
| Shared                 | app/Modules/Shared     | Audit, exceptions, messaging, VOs       | Both      |
| Landlord / Billing     | app/Landlord           | Tenants, plans, features, subscriptions, Stripe | Central   |

### Reusable vs branch-specific
- **Reusable core:** Shared (audit, exceptions, buses, value objects); User model (same class, different connection); Spatie Permission (central + tenant tables).
- **Tenant-only:** All of app/Modules/* (Catalog, Cart, Checkout, Inventory, Orders, Payments); Filament Tenant panel; tenant migrations.
- **Landlord-only:** app/Landlord/*; Filament Landlord panel; central migrations (tenants, plans, subscriptions, permissions, audit).
- **No duplicated business logic** between “branches” — tenant and landlord are clearly separated by namespace and DB.

### Duplication / consistency
- **Categories:** Tenant-only (tenant DB); Catalog module has Product only; CategoryResource is Filament-only (no Catalog API for categories in the explored routes).
- **Customer:** No “Customer” entity in Modules; `CustomerSummaryModel` is a view (customer_summaries) for Filament; order has `customer_email`.
- **Rules vs code:** `.cursor/rules/04-landlord-vs-tenant.md` mentions “tenant_users” and “Identity module”; codebase uses single `User` model in both DBs with connection switching — rule and implementation diverge.

---

## 3. COMPLETED FEATURES (FROM CODE ONLY)

| Feature                     | Implemented | Evidence |
|----------------------------|------------|----------|
| Authentication             | Yes        | Laravel auth (web guard), session; Filament login for both panels. |
| Authorization / RBAC       | Yes        | Spatie Permission; LandlordRole/TenantRole; LandlordPermissions/TenantPermissions; policies; role/permission middleware. |
| Multi-tenant (stores)      | Yes        | Stancl Tenancy; DB per tenant; domain/subdomain identification. |
| Multi-vendor               | No         | No vendor/seller entity or multi-vendor logic. |
| Multi-branch               | No         | No branch/location entity. |
| Product management         | Yes        | Catalog module (API + Filament); products table; categories (Filament + tenant migration). |
| Order lifecycle            | Yes        | Orders module: create, add item, confirm, pay, ship, cancel; order_items; status. |
| Invoice system             | No         | No invoice entity, numbering, or PDF generation. |
| Payment integration        | Yes        | Stripe (Landlord: checkout, webhooks, portal); Payments module (create, confirm, refund); payments table. |
| CMS                        | No         | No pages, blocks, or content entities. |
| API structure              | Yes        | REST-style under api/v1 and api/landlord; FormRequests; JsonResource/Resource responses. |
| Admin panel                | Yes        | Filament: Landlord (/admin), Tenant (/dashboard); many resources, pages, widgets. |
| CI/CD                      | No         | No .github/workflows or other pipeline config in repo. |
| Queue workers              | Yes        | Queue config; LogAuditEntry job; some listeners implement ShouldQueue; docs mention Horizon. |
| Caching                    | Yes        | config/cache.php; Spatie Permission cache; tenancy cache bootstrapper. |
| File storage               | Yes        | config/filesystems.php; tenancy filesystem bootstrapper; no S3-specific audit logic in scope. |
| PDF handling               | No         | No PDF library or invoice/receipt PDF in codebase. |
| Multi-language             | No         | No i18n/locale switching or translation files for app content. |
| Currency handling         | Partial    | price_minor_units, currency on products/orders/carts; single-currency flows (no multi-currency UX). |
| Tax logic                  | No         | No tax entity, tax calculation, or tax-inclusive fields. |
| Idempotency                | Yes        | IdempotencyMiddleware; idempotency_keys table. |
| Audit logging              | Yes        | Tenant + landlord audit logs; async job; observers; Filament read-only resources; prune command. |
| Plan/feature limits        | Yes        | FeatureResolver, tenant_limit(), tenant_feature(); products_limit enforced in ProductResource create. |
| Stripe webhooks            | Yes        | StripeWebhookController; stripe_events; checkout.session.completed, invoice.payment_failed, customer.subscription.*. |

---

## 4. DATABASE DESIGN

### Inferred ERD overview

**Central (landlord) DB:**
- **users** — id, name, email, password, is_super_admin, ...
- **tenants** — id (uuid), name, slug, status, plan_id, stripe_customer_id, data, timestamps, soft deletes
- **domains** — id, domain, tenant_id, is_primary
- **plans** — id (uuid), name, code, price, billing_interval, is_active, soft deletes
- **features** — id (uuid), code, description, type, soft deletes
- **plan_features** — plan_id, feature_id, value
- **subscriptions** — id (uuid), tenant_id, plan_id, stripe_subscription_id, status, current_period_*, cancel_at_period_end, past_due_at, ...
- **stripe_events** — event_id, processed_at (idempotency)
- **idempotency_keys** — tenant_id, key, endpoint, response_hash, status_code
- **permissions, roles, model_has_permissions, model_has_roles, role_has_permissions** (Spatie)
- **landlord_audit_logs** — user_id, event_type, model_type, model_id, description, properties, ip_address, user_agent, tenant_id, created_at
- **activity_log** (Spatie, if used on central)
- **sessions, cache, jobs, failed_jobs, job_batches, password_reset_tokens, personal_access_tokens**

**Tenant DB (per tenant):**
- **users** — same schema as central (connection switches)
- **products** — id (uuid), tenant_id, name, slug, description, price_minor_units, currency, is_active, soft deletes
- **categories** — id, tenant_id, name, slug, parent_id, status
- **stock_items** — id, tenant_id, product_id, quantity, reserved_quantity, low_stock_threshold
- **orders** — id (uuid), tenant_id, customer_email, status, total_amount, currency, internal_notes, soft deletes
- **order_items** — order_id, product_id, quantity, unit_price_*, total_price_*
- **carts** — id, tenant_id, customer_email, session_id, status, total_amount, currency, soft deletes
- **cart_items** — cart_id, product_id, quantity, unit_price_*, total_price_*
- **payments** — id, tenant_id, order_id, amount, currency, status, provider, provider_payment_id
- **customer_summaries** — view (tenant_id, email, order_count, total_spent)
- **permissions, roles, model_has_*, role_has_permissions** (Spatie, tenant-scoped)
- **tenant_audit_logs** — same shape as landlord_audit_logs without tenant_id
- **activity_logs** (tenant activity log table)

### Main relationships
- **Central:** tenants → domains (1:n); tenants → plan (n:1); plans → plan_features → features; subscriptions → tenant, plan; users (central) — no direct FK to tenants in schema.
- **Tenant:** products → stock_items (1:1 or 1:n); orders → order_items; carts → cart_items; orders → payments; users (tenant) — roles/permissions via Spatie morph pivot.

### Polymorphic relations
- **Spatie Permission:** model_has_roles, model_has_permissions (model_type, model_id) — used by User (and any other guard model).
- **Audit logs:** model_type, model_id on tenant_audit_logs / landlord_audit_logs (generic subject).
- No other polymorphic relations found in app code.

### Pivot tables
- **plan_features** — plan_id, feature_id, value
- **role_has_permissions** — permission_id, role_id
- **model_has_roles** — role_id, model_id, model_type
- **model_has_permissions** — permission_id, model_id, model_type
- **order_items**, **cart_items** — not pivots but join tables with extra columns.

### Scaling bottlenecks (inferred)
- **Tenant enumeration:** One DB per tenant scales with tenant count (connection count, migration runs).
- **Central DB:** All landlord data and (if used) central users in one DB; subscriptions/stripe_events can grow.
- **No sharding:** Central is single DB; no read replicas or sharding in code.
- **Queue:** Single queue (or per-tenant via tenancy); one Horizon pool unless configured otherwise.

---

## 5. API DESIGN ANALYSIS

### Style
- **REST-like.** Resources (products, cart, orders, payments, inventory, plans, subscriptions); GET for read, POST/PATCH/DELETE for write. Some RPC-style actions (e.g. POST `products/{id}/activate`, POST `checkout/confirm-payment`).
- **Consistency:** Plural nouns under api/v1 (catalog/products, cart, checkout, orders, payments, inventory); landlord under api/landlord (plans, subscriptions, billing/*).

### Validation
- **FormRequest** used for: StoreProductRequest, UpdateProductPriceRequest; CreateCartRequest, AddCartItemRequest, UpdateCartItemRequest; CheckoutRequest, ConfirmCheckoutPaymentRequest; CreateOrderRequest, AddOrderItemRequest; CreatePaymentRequest, ConfirmPaymentRequest; CreateStockRequest, IncreaseStockRequest, DecreaseStockRequest, ReserveStockRequest, ReleaseStockRequest, SetLowStockThresholdRequest; CreatePlanRequest, SubscribeTenantRequest, CancelSubscriptionRequest.
- **Inline validation** not audited everywhere; FormRequest is the standard for API input.

### Versioning
- **Prefix only.** `/api/v1/` for tenant APIs; no version in landlord prefix (`/api/landlord/`). No header or vendor versioning.

### Response formatting
- **JsonResource / Resource:** ProductResource, CartResource, OrderResource, PaymentResource, StockItemResource, CheckoutResponseResource; PlanResource, SubscriptionResource (Landlord). Collections via `Resource::collection()`.
- **Direct JSON** in some closures (e.g. billing success/cancel/portal return).

### Auth on API
- **api/v1:** No `auth:sanctum` (or other auth) on v1 routes in the included files — tenant APIs are **unauthenticated**; tenancy is by subdomain only.
- **api/landlord:** No auth middleware on billing routes — **plans, subscriptions, checkout, webhook** are unauthenticated from route definition. Webhook is expected to be public; others may be intended for server-to-server or undocumented auth.
- **GET /api/user:** Uses `auth:sanctum` (only route with explicit auth in api.php).

---

## 6. FRONTEND STRUCTURE

### Framework
- **Blade + Filament.** No SPA framework (no React/Vue/Svelte in app code). Filament provides the admin UIs (Landlord + Tenant panels).
- **Vite** referenced in composer scripts (`npm run dev`, `npm run build`) — standard Laravel frontend build.

### State management
- **Server-rendered.** Filament handles state via Livewire; no client-side state store. Public site is a single `welcome` view.

### API integration
- **Tenant storefront not in repo.** API v1 (catalog, cart, checkout, orders, payments, inventory) is built for consumption by an external client (e.g. storefront). No in-repo storefront that calls these APIs.
- **Filament** talks to the app via server-side PHP (no separate API layer for panels).

### Multi-tenant handling
- **Tenant panel:** Domain-based; Filament at `/dashboard` with InitializeTenancyByDomain. One domain (or subdomain) per tenant for admin.
- **API v1:** Subdomain-based; tenant from subdomain. No tenant in path or header.

### Environment / deployment (frontend)
- Standard Laravel env; no separate frontend env file. Deployment strategy not defined in code (see DevOps section).

---

## 7. DEVOPS & INFRASTRUCTURE

### Deployment strategy
- **Docs only.** `docs/DEPLOYMENT_BLUEPRINT.md`: Nginx + PHP-FPM, Redis (cache + queue), MySQL (central + tenant DBs), S3 or local storage, Horizon, cron for scheduler.
- **No automated pipeline** in repo (no .github, GitLab CI, or similar).

### Environment handling
- **.env** (and .env.example) standard Laravel; docs (e.g. ENV_PRODUCTION_CHECKLIST, PRODUCTION_CHECKLIST_TENANCY) describe production settings. No multi-environment code paths beyond config/env.

### Branching strategy
- **Not defined in repo.** Git history/branching not audited; only current state.

### Docker
- **Laravel Sail** in require-dev; no custom Dockerfile or docker-compose in the listed structure. Traditional VPS + Nginx/PHP-FPM is the documented target.

### Domain mapping
- **Tenancy:** Central domains in config; tenant identification by domain or subdomain (API vs Filament). No dynamic domain routing config in code beyond Stancl.

---

## 8. SECURITY ANALYSIS

### Auth flow
- **Web (Filament):** Session-based (web guard); login provided by Filament. Landlord: EnsureSuperAdmin (landlord roles or is_super_admin). Tenant: CheckTenantStatus (suspended → logout + redirect).
- **API:** Only `GET /api/user` uses `auth:sanctum`. Rest of api/v1 and api/landlord have **no auth middleware** in the route files — tenant APIs are callable by anyone who can reach the subdomain; landlord billing endpoints (except webhook) are callable without auth unless applied elsewhere.

### Token handling
- **Sanctum** configured; personal_access_tokens migration present. Usage is minimal (single route).
- **Stripe webhook:** Signature verification expected in StripeWebhookController (not re-verified here); stripe_events table for idempotency.

### API key exposure risk
- **Stripe keys** in config/services (env); no keys in repo. Standard env-based handling.
- **Landlord API** (plans, subscribe, cancel) — if called from untrusted clients without auth, any client could trigger subscribe/cancel if they know tenant/plan IDs.

### File protection
- **Tenancy** filesystem bootstrapper prefixes paths by tenant. No explicit “signed URL” or role-check for file access in the audited paths; Filament handles its own asset delivery.

### Role-based access
- **Enforced in Filament** via policies and canAccess() (e.g. Audit log: owner / super_admin only). RBAC (Spatie) and policies registered; middleware `role` and `permission` available. API routes do not use them.

---

## 9. TECHNICAL DEBT & RISKS

### Tight coupling
- **FeatureResolver / tenant_limit / tenant_feature** used directly in Filament (e.g. ProductResource create) and helpers — tenant modules depend on Landlord feature resolution (acceptable for SaaS but creates a dependency from tenant to central).
- **Stripe** logic in Landlord; checkout flow in tenant calls payment flow — cross-concern but bounded.

### God classes
- No single class >~500 lines audited. **CheckoutOrchestrator** and **StripeWebhookController** coordinate many steps but are focused.

### Fat controllers
- **StripeWebhookController** and **BillingCheckoutController** contain substantive logic; could be moved to dedicated service classes for clarity and testing.
- API controllers are generally thin (dispatch command/handler, return resource).

### Duplicate business logic
- **Plan/limit enforcement** appears in ProductResource (create) and possibly elsewhere; centralizing in a single “plan limit check” service would reduce duplication.
- **Tenant context checks** (tenant('id') !== null) repeated in many places; could be middleware or base policy.

### Missing abstraction layers
- **API v1:** No tenant-scoped auth; no rate limiting visible in routes; no API versioning beyond path prefix.
- **Landlord API:** No auth on plans/subscriptions/checkout — risk if exposed to internet.

### Hard-coded config
- **Central domains** hard-coded in tenancy.php (127.0.0.1, localhost, sass-ecommerce.test).
- **Queue name** for audit (`low`) in config with env override — acceptable.

### Scalability limitations
- **DB per tenant** — operational burden at high tenant count (migrations, backups).
- **Single central DB** — no read replicas or sharding in code.
- **No event sourcing** — audit is append-only log; no replay or event stream for future analytics.

---

## 10. ARCHITECTURAL MATURITY SCORE

| Dimension       | Score (1–10) | Notes |
|-----------------|--------------|--------|
| **Scalability** | 6            | DB-per-tenant scales but ops cost grows. Central DB and queue are single-node. No horizontal scaling of central or event stream. |
| **Maintainability** | 8     | Clear modules, CQRS-style commands, repositories, DTOs, FormRequests, policies. Some inline logic in Filament and webhook controller. |
| **Extensibility** | 7            | New tenant features via modules; plan/feature flags in place. Landlord/tenant split clear. Adding new domains or payment providers requires touching several layers. |
| **Security** | 5             | RBAC and policies good for Filament. API v1 and landlord API largely unauthenticated; webhook and idempotency present. |
| **SaaS readiness** | 8     | Tenancy, plans, limits, billing, Stripe, two panels, audit, RBAC. Gaps: no tenant auth on API, no multi-currency/tax/invoicing. |

---

## 11. RECOMMENDED REFACTOR ROADMAP

### Immediate fixes
1. **Secure API v1:** Add auth (e.g. Sanctum or API key) to tenant API routes and document expected client (storefront) auth.
2. **Secure Landlord API:** Add auth (e.g. API token or internal-only network) to `api/landlord` routes except webhook and possibly success/cancel/portal return.
3. **Central domains:** Move central_domains to env (e.g. `CENTRAL_DOMAINS`) so production domains are not code changes.
4. **Fix FeatureResource (Filament):** Resolve type compatibility with Filament 5 ($navigationGroup / $navigationIcon / form signature) so Landlord panel and tests run without fatal errors.

### Medium-term refactors
1. **Plan limit enforcement:** Single service (e.g. `PlanLimitEnforcer::ensureWithinLimit('products_limit', $currentCount)`) used from ProductResource and any future limited resources; throw plan exception from one place.
2. **Webhook and billing controllers:** Extract Stripe handling and checkout session creation into service classes; keep controllers thin.
3. **API versioning:** Document and enforce versioning (e.g. /api/v1/ always required); add header or path contract for future v2.
4. **Tenant API auth:** Define storefront identity (guest vs customer); add Sanctum or session for dashboard/API if same app serves both.
5. **Tests:** Run full test suite (including Landlord) after Filament fixes; add tests for unauthenticated API access and for auth when added.

### Long-term architecture improvements
1. **Central DB scaling:** Introduce read replicas or split read-heavy landlord reads; consider event/canonical log for subscription/tenant events for analytics.
2. **Tenant DB strategy:** Evaluate schema-per-tenant for PostgreSQL to reduce DB count; or keep DB-per-tenant and invest in automation (migrations, backups, monitoring).
3. **Event streaming:** Use the audit “event streaming” placeholder in config to optionally publish high-value events (order placed, subscription changed) to a bus or queue for external consumers and analytics.
4. **Multi-currency / tax / invoicing:** If required, add bounded contexts (e.g. Invoicing, Tax) with clear integration points to Orders and Billing.
5. **Documentation:** Replace default README with project-specific overview, architecture diagram, and runbook (migrate, seed, tenant create, queue, scheduler).

---

*End of technical audit. All conclusions are based on the current codebase and config; no assumptions were made about intent or future work.*
