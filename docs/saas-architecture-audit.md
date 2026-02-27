# SaaS Architecture Audit — Complete System Overview

**Project:** saas-ecommerce  
**Audit type:** Full deep technical audit  
**Date:** 2025-02-27

---

## 1. PROJECT ARCHITECTURE OVERVIEW

### 1.1 Architecture style

**Classification: Modular monolith with database-per-tenant multi-tenancy.**

- **Monolith:** Single deployable Laravel application; no separate service processes.
- **Modular:** Bounded contexts exist under `app/Modules/` (Catalog, Cart, Checkout, Orders, Payments, Inventory, Financial, Shared) and `app/Landlord/` (Billing, Models, Services, Policies). Filament panels are split into `Filament/Tenant` and `Filament/Landlord`.
- **Database-per-tenant:** Stancl Tenancy v3 with `DatabaseTenancyBootstrapper`; each tenant has a dedicated database (`tenant{uuid}`). Central (landlord) DB holds `tenants`, `plans`, `subscriptions`, `plan_features`, `features`, `domains`, `users`, `landlord_audit_logs`.
- **Hybrid elements:** Landlord API (`/api/landlord/*`) and tenant API (`/api/v1/*`) live in the same app; tenant resolution is by **domain** (subdomain or full domain), not by path for API.

### 1.2 Tech stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8.2, Laravel 12, Filament 5 (admin UIs) |
| **Frontend** | Vite 7, Tailwind 4, Livewire (via Filament); no separate SPA in repo |
| **Database** | SQLite/MySQL/PostgreSQL (env-driven); central + per-tenant DBs |
| **Queue** | Laravel queues; Horizon (Redis) for production |
| **Cache** | Laravel Cache (Redis/file); Stancl `CacheTenancyBootstrapper` (tag-based tenant isolation) |
| **Storage** | Local/public disks with `FilesystemTenancyBootstrapper` (tenant-suffixed paths) |
| **Auth** | Laravel Sanctum (API: bearer + stateful); guards: `web` (User), `tenant_web`, `customer` (Customer model) |
| **Multi-tenancy** | Stancl Tenancy 3.9 (database, cache, filesystem, queue, Redis bootstrappers) |
| **Notable packages** | Spatie Permission, Activity Log, Media Library, Model States, Query Builder, Webhook Server; Stripe; Laravel Horizon 5 |

### 1.3 Directory structure and module organization

```
app/
├── Console/Commands/          # TenantAddDomainCommand, ReseedCommand
├── Enums/                     # LandlordRole
├── Events/                    # JobFailed; Financial OrderPaid, OrderRefunded
├── Filament/
│   ├── Landlord/              # Panel "landlord", path /admin
│   │   └── Resources/         # Tenant, Plan, Subscription, Feature, AuditLog
│   └── Tenant/               # Panel "tenant", path /dashboard
│       ├── Pages/             # Billing, DomainSettings, StoreSettings, MarketingPlaceholder
│       ├── Resources/         # Product, Order, Customer, Invoice, Currency, Inventory, etc.
│       └── Widgets/           # Revenue, Orders, LowStock, Conversion, etc.
├── Helpers/                   # tenant_features.php, tenant_cache.php
├── Http/
│   ├── Controllers/           # Health, Welcome; Api/UserController; Api/V1/Customer/*; Api/ReportsController
│   └── Middleware/            # EnsureCentralDomain, CheckTenantStatus, EnsureSuperAdmin,
│                              # EnsureTenantSubscriptionIsActive, EnsureTenantNotSuspended,
│                              # CheckTenantFeature, EnsureSystemNotReadOnly, IdempotencyMiddleware,
│                              # EnsureUserHasRole, EnsureUserHasPermission
├── Jobs/                      # LogAuditEntry, ReconcileFinancialDataJob
├── Landlord/
│   ├── Billing/               # Application/Services (BillingService), Domain/Events,
│   │   └── Infrastructure/Http/Controllers (Plan, Subscription)
│   ├── Http/Controllers/     # BillingCheckout, BillingCallback, StripeWebhook
│   ├── Listeners/             # AssignDefaultPlan, CreateTenantDatabase
│   ├── Models/                # Tenant, Plan, Subscription, Feature, Domain
│   ├── Policies/              # PlanPolicy, SubscriptionPolicy, TenantPolicy
│   └── Services/              # FeatureResolver, FeatureUsageService, StripeService
├── Listeners/                 # OrderPaid, SendOrderConfirmationEmail, SubscriptionCancelled;
│   ├── Financial/             # CreateFinancialTransaction, SyncFinancialOrderOnPaymentSucceeded,
│   │                          # CreateLedgerTransactionOnOrderPaid, CreateLedgerReversalOnOrderRefunded, AuditLogOrderStatus
│   └── Invoice/               # CreateInvoiceOnOrderPaidListener
├── Models/                    # User (central); Customer, Invoice, Financial/*, Currency/*, Inventory/*, Promotion/*, Ledger/*, Refund
├── Modules/
│   ├── Cart/                  # Domain (Cart, CartItem), Application (Handlers, Services), Infrastructure (Persistence, Http/Api)
│   ├── Catalog/               # Domain (Product, Events), Application (Handlers), Infrastructure (Persistence, Http/Api), Providers
│   ├── Checkout/              # Application (CheckoutOrchestrator, Commands, Contracts, DTOs), Infrastructure (Http, Services)
│   ├── Financial/             # Application/Services (FinancialReconciliationService)
│   ├── Inventory/             # Domain (Events), Infrastructure (Persistence, Http/Api)
│   ├── Orders/                # Domain (Order, OrderItem, Repositories), Application (Handlers, Commands), Infrastructure (Persistence)
│   ├── Payments/              # Domain (Payment, PaymentGateway), Application (Handlers, Services), Infrastructure (Gateways, Persistence)
│   └── Shared/                # Domain (Exceptions, ValueObjects), Infrastructure (Audit, Persistence, Messaging)
├── Observers/                 # Product, Order, StockItem, User, Plan, Feature, Subscription, Tenant
├── Policies/                  # Product, Order, Customer, CustomerIdentity, Invoice, Currency, Inventory, InventoryLocation
├── Providers/                 # AppServiceProvider, EventServiceProvider, TenancyServiceProvider,
│   └── Filament/              # TenantPanelProvider, LandlordPanelProvider
├── Services/                  # Currency (CurrencyService, CurrencyConversionService), Customer (LinkGuestOrders, PromotionEligibility),
│   ├── Invoice/               # InvoiceService, InvoiceNumberGenerator, InvoicePdfGenerator
│   ├── Inventory/             # InventoryTransferService, InventoryAllocationService
│   ├── Promotion/             # PromotionResolverService, RecordPromotionUsageService, PromotionEvaluationService
│   └── Reporting/             # RevenueReportService, TaxReportService, TopProductsReportService, ConversionReportService
└── Support/                   # Instrumentation
```

- **DDD/Clean-style boundaries:** Partially applied in `Modules/*`: Domain (entities, events, value objects, exceptions), Application (handlers, services, contracts, commands), Infrastructure (Persistence, Http, gateways). No strict ports/adapters isolation; Infrastructure references Eloquent models and Laravel.
- **Landlord:** Billing and tenant lifecycle live under `Landlord/` with its own Billing domain (events, application services) and Infrastructure (HTTP, Stripe).

### 1.4 Service layers, repositories, controllers, events, listeners, jobs, middleware

| Layer | Location / mechanism |
|-------|------------------------|
| **Controllers** | `app/Http/Controllers` (Health, Welcome, Api/User, Api/Reports, Api/V1/Customer/*); `app/Modules/*/Infrastructure/Http` or `Http/Api/Controllers` (Catalog, Cart, Checkout, Orders, Payments, Inventory); `app/Landlord/Http/Controllers`, `Landlord/Billing/Infrastructure/Http/Controllers` |
| **Services** | `app/Services/*`, `app/Landlord/Services/*`, `app/Modules/*/Application/Services` (e.g. CheckoutOrchestrator, PaymentService, FinancialReconciliationService) |
| **Repositories** | `app/Modules/*/Infrastructure/Persistence/*Repository.php` (Eloquent*Repository); Domain contracts in `Modules/*/Domain/Repositories` or Application contracts |
| **Events** | `app/Events`, `app/Modules/*/Domain/Events`, `app/Landlord/Billing/Domain/Events`, `app/Landlord/Events` |
| **Listeners** | `app/Listeners`, `app/Listeners/Financial`, `app/Listeners/Invoice`, `app/Landlord/Listeners`; registered in `EventServiceProvider` and TenancyServiceProvider |
| **Jobs** | `app/Jobs/LogAuditEntry`, `app/Jobs/ReconcileFinancialDataJob`; Stancl pipeline (CreateDatabase, MigrateDatabase, DeleteDatabase) |
| **Middleware** | Global: `EnsureSystemNotReadOnly`; Aliases: `feature`, `subscription.active`, `tenant.not_suspended`, `idempotency`, `role`, `permission`. Panel-specific: Landlord `EnsureCentralDomain`, `EnsureSuperAdmin`; Tenant `PreventAccessFromCentralDomains`, `InitializeTenancyByDomain`, `CheckTenantStatus` |

### 1.5 Architectural diagram (text)

```
                    ┌─────────────────────────────────────────────────────────────────┐
                    │                     Laravel Application                          │
                    └─────────────────────────────────────────────────────────────────┘
                                              │
              ┌───────────────────────────────┼───────────────────────────────┐
              │                               │                               │
              ▼                               ▼                               ▼
    ┌─────────────────┐             ┌─────────────────┐             ┌─────────────────┐
    │  Central Domain │             │  Tenant Domain  │             │  Tenant Domain  │
    │  (Landlord)     │             │  (API v1)       │             │  (Filament)     │
    │  /admin         │             │  /api/v1/*      │             │  /dashboard     │
    └────────┬────────┘             └────────┬────────┘             └────────┬────────┘
             │                               │                               │
             │ EnsureCentralDomain           │ InitializeTenancyByDomain     │ InitializeTenancyByDomain
             │ auth: web (User)               │ PreventAccessFromCentral     │ PreventAccessFromCentral
             │ EnsureSuperAdmin               │ auth: sanctum / customer     │ auth: web (User*)
             │                               │                               │ CheckTenantStatus
             ▼                               ▼                               ▼
    ┌─────────────────┐             ┌─────────────────┐             ┌─────────────────┐
    │ Central DB      │             │ Tenant DB       │             │ Tenant DB       │
    │ tenants, plans  │             │ orders, carts   │             │ (same as API)   │
    │ subscriptions   │             │ products, etc.  │             │                 │
    │ users, domains  │             │                 │             │                 │
    └─────────────────┘             └─────────────────┘             └─────────────────┘
             │                               │                               │
             │ FeatureResolver ─────────────┼───────────────────────────────┘
             │ (Subscription/Plan)          │
             │ Cache (tenant:{id}:features)  │  CacheTenancyBootstrapper
             │ QueueTenancyBootstrapper     │  FilesystemTenancyBootstrapper
             └──────────────────────────────┴───────────────────────────────────────────┘
```

\* Tenant Filament uses guard `web` with provider `users` (App\Models\User). User model uses central connection, so tenant dashboard staff are central users; tenant DB holds tenant-specific data (products, orders, customers).

---

## 2. FEATURE & MODULE INVENTORY

| Module / feature | Purpose | Main models/entities | Controllers / services | APIs exposed | DB tables (tenant unless noted) | Events emitted | Jobs | Dependencies | Consumer |
|------------------|---------|----------------------|-------------------------|--------------|----------------------------------|----------------|------|---------------|----------|
| **Landlord / Billing** | Plans, subscriptions, Stripe billing, webhooks | Plan, Subscription, Tenant, Feature (central) | PlanController, SubscriptionController, BillingCheckoutController, BillingCallbackController, StripeWebhookController; BillingService | GET/POST /api/landlord/plans, /subscriptions, /billing/checkout, /billing/webhook, success/cancel/portal | Central: tenants, plans, subscriptions, plan_features, features, domains | PlanCreated/Activated/Deactivated, SubscriptionCreated/Activated/Cancelled/PastDue | CreateDatabase, MigrateDatabase (Stancl) | Stripe, central DB | Landlord |
| **Tenant CRUD / Domains** | Tenant lifecycle, domains | Tenant, Domain (central) | Filament Landlord TenantResource; TenantAddDomainCommand | — | Central | TenantCreated, TenantDeleted | CreateTenantDatabase (listener), DeleteDatabase (Stancl) | Landlord | Landlord |
| **Catalog** | Products, categories | Product (entity), ProductModel, CategoryModel | ProductController (API); Filament ProductResource, CategoryResource | GET/POST/PATCH /api/v1/catalog/products, activate/deactivate | products, categories (tenant_id) | ProductCreated, ProductActivated, ProductDeactivated, ProductPriceChanged | — | Shared (exceptions), FeatureResolver (tenant_feature) | Tenant (API + Filament), Public (read products) |
| **Cart** | Cart and cart items | Cart, CartItem (domain); CartModel | CartController (API) | POST/GET/PUT/DELETE /api/v1/cart, items, clear, convert, abandon | carts, cart_items (tenant_id) | CartCreated, CartItemAdded/Updated/Removed, CartCleared, CartConverted, CartAbandoned | — | Catalog (product), Orders (order creation) | Tenant (API) |
| **Checkout** | Checkout flow, payment initiation | — | CheckoutController; CheckoutOrchestrator, CheckoutOrderService, CheckoutPaymentService, CheckoutCartService, CheckoutInventoryService | POST /api/v1/checkout, confirm-payment | — | — | — | Cart, Orders, Payments, Inventory, Promotion, Shared (TransactionManager) | Tenant (API) |
| **Orders** | Order lifecycle | Order, OrderItem (domain); OrderModel, CustomerSummaryModel | OrderController (API); Filament OrderResource | POST/GET /api/v1/orders, items, confirm, pay, ship, cancel | orders, order_items, customer_summaries (view) (tenant_id) | OrderCreated, OrderConfirmed, OrderPaid, OrderShipped, OrderCancelled, OrderItemAdded | — | Catalog, Payments, Financial (listeners) | Tenant (API + Filament) |
| **Payments** | Payment creation, confirm, refund, cancel | Payment (entity); PaymentModel | PaymentController (API); PaymentService, StripePaymentGateway | POST /api/v1/payments, confirm, refund, cancel | payments (tenant_id) | PaymentCreated, Authorized, Succeeded, Failed, Refunded, Cancelled | — | Stripe, Orders, Financial (listeners) | Tenant (API) |
| **Inventory** | Stock levels, reserve/release, multi-location | StockItemModel; InventoryLocation, InventoryLocationStock, InventoryMovement, etc. | StockController (API); Filament InventoryResource, MultiLocation/* | POST/GET/PATCH /api/v1/inventory | stock_items, inventory_locations, inventory_location_stocks, movements, reservations, transfers (tenant_id) | StockCreated, Increased, Decreased, Reserved, Released, LowStockReached | — | Catalog | Tenant (API + Filament) |
| **Financial** | Financial orders, transactions, ledger, reconciliation | FinancialOrder, FinancialOrderItem, FinancialTransaction, TaxRate; Ledger*, LedgerEntry, LedgerTransaction | Filament FinancialOrderResource, FinancialTransactionResource, TaxRateResource; FinancialReconciliationService | — | financial_orders, financial_order_items, financial_order_tax_lines, financial_transactions, tax_rates; ledger_*, refunds (tenant_id) | (internal; OrderPaid, OrderRefunded from Events) | ReconcileFinancialDataJob | Orders, Payments, Invoices | Tenant (Filament) |
| **Invoicing** | Invoices, credit notes, PDF | Invoice, InvoiceItem, InvoicePayment, CreditNote | InvoiceService, InvoiceNumberGenerator, InvoicePdfGenerator; Filament InvoiceResource; InvoicePdfDownloadController | GET tenant route: invoices/{id}/pdf | invoices, invoice_items, invoice_payments, credit_notes, invoice_number_sequence (tenant_id) | — | — | Orders (CreateInvoiceOnOrderPaidListener) | Tenant (Filament + tenant route) |
| **Customer identity** | Customer auth, profile, addresses, GDPR | Customer, CustomerAddress, CustomerSession | AuthController, ProfileController, AddressController, AccountController, PasswordController (API); Filament CustomerIdentityResource, CustomerResource | POST/GET/PATCH/DELETE /api/v1/customer (register, login, me, profile, addresses, password, export, account) | customers, customer_addresses, sessions, password_reset_tokens, personal_access_tokens (tenant) | — | — | — | Tenant / Public (register, login) |
| **Reports (API)** | Revenue, tax, products, conversion | — | ReportsController | GET /api/v1/reports/revenue, tax, products, conversion | (reads orders, financial, products) | — | — | OrderModel, reporting services | Tenant (API) |
| **Currency** | Currencies, exchange rates, tenant settings | Currency, ExchangeRate, TenantCurrencySetting, TenantEnabledCurrency | CurrencyService, CurrencyConversionService; Filament CurrencyResource, ExchangeRateResource, TenantCurrencySettingsResource | — | currencies, exchange_rates, tenant_currency_settings, tenant_enabled_currencies | — | — | config/currency.php | Tenant (Filament) |
| **Promotions** | Promotions, coupons, usage | Promotion, CouponCode, PromotionUsage | PromotionResolverService, PromotionEvaluationService, RecordPromotionUsageService, CustomerPromotionEligibilityService | — | promotions, coupon_codes, promotion_usages (tenant) | — | — | Orders, Checkout | Tenant (Filament / checkout) |
| **Audit** | Activity log, tenant/landlord audit | TenantAuditLog, LandlordAuditLog; ActivityLogModel | AuditLogger (Shared); Observers (Product, Order, StockItem, User, Plan, etc.) | — | tenant_audit_logs (tenant_id), landlord_audit_logs (central) | — | LogAuditEntry (optional) | — | Tenant + Landlord (Filament) |
| **Permissions (tenant)** | Roles/permissions per tenant | (Spatie) | Filament RoleResource, PermissionResource | — | permission tables (tenant) | — | — | — | Tenant (Filament) |
| **User (central)** | Landlord + tenant dashboard staff | User | UserController (API) | GET /api/user (auth:sanctum) | users (central) | — | — | — | Landlord / Tenant (ambiguous) |

---

## 3. FEATURE INTERACTION MAP

### 3.1 Communication patterns

- **Checkout → Cart, Orders, Payments, Inventory, Promotion:** Direct service/repository calls (CheckoutOrchestrator injects CartService, OrderService, PaymentService, InventoryService, PromotionResolverService, PromotionEvaluationService). Tight coupling within the same process.
- **Orders ↔ Financial / Invoices / Payments:** Event-driven. `OrderPaid` (or PaymentSucceeded) triggers CreateInvoiceOnOrderPaidListener, CreateLedgerTransactionOnOrderPaidListener, SyncFinancialOrderOnPaymentSucceededListener; OrderRefunded triggers CreateLedgerReversalOnOrderRefundedListener. Financial and Audit listeners subscribe to order/payment events.
- **Landlord → Tenant lifecycle:** TenantCreated → CreateTenantDatabase (listener), JobPipeline (CreateDatabase, MigrateDatabase). TenantDeleted → DeleteDatabase. AssignDefaultPlan on TenantCreated.
- **Subscription / feature checks:** FeatureResolver (and tenant_feature() / tenant_limit()) reads from central DB (Subscription, Plan, plan_features). Used by CheckoutOrchestrator (multi_location_inventory), CreateProduct (products_limit), FeatureUsageService, and middleware (CheckTenantFeature, EnsureTenantSubscriptionIsActive).

### 3.2 Direct vs events vs queues

| From | To | Mechanism |
|------|-----|-----------|
| Checkout | Cart, Orders, Payments, Inventory, Promotion | Direct (services/repos) |
| Payments (PaymentSucceeded) | Financial, Invoice, Order (confirmation email) | Events + listeners |
| Order (status changes) | Financial (transaction), Audit | Event subscribers |
| Landlord (tenant created) | Tenant DB | Queue (CreateDatabase, MigrateDatabase) or sync |
| ReconcileFinancialDataJob | All tenants | Direct loop with tenancy()->initialize($tenant) |

### 3.3 Coupling and circular dependencies

- **Tight coupling:** CheckoutOrchestrator depends on many modules (Cart, Orders, Payments, Inventory, Promotion). Modules share Shared (exceptions, value objects, audit). Landlord FeatureResolver is used from tenant context (cross-DB).
- **Loose coupling:** Order/Financial/Invoice via events; Landlord Billing vs tenant features via central DB and cache.
- **Circular risk:** No obvious circular dependency between modules; Shared is a leaf. Landlord does not depend on tenant modules; tenant code depends on Landlord (FeatureResolver, Subscription) for feature/limit checks.

### 3.4 Dependency graph (text)

```
Landlord (Billing, Tenant CRUD)
  └── Central DB, Stripe

Tenant API / Filament
  ├── Catalog ──────────────────────────────────────────┐
  ├── Cart ───► Catalog, Orders (order creation)        │
  ├── Checkout ──► Cart, Orders, Payments, Inventory,   │
  │               Promotion, Shared                      │
  ├── Orders ──► Catalog, Payments (listeners)           │
  ├── Payments ──► Stripe                               │
  ├── Inventory ──► Catalog                              │
  ├── Financial ──► Orders (events), Invoices           │
  ├── Invoicing ──► Orders (listener)                    │
  ├── Customer identity                                 │
  ├── Currency                                          │
  ├── Promotions ──► Orders                             │
  └── Shared (exceptions, audit, value objects) ◄────────┘

Landlord FeatureResolver / Subscription
  └── Used by: Catalog (limit), Checkout (feature), Filament (usage), middleware
```

---

## 4. MULTI-TENANCY ANALYSIS

### 4.1 Tenancy model

- **Database-per-tenant.** Config: `config/tenancy.php` → `database.prefix` = `tenant`, `suffix` = `''` → DB name `tenant{uuid}`. Central connection from `env('DB_CONNECTION')` / `config('database.default')`.
- **Migrations:** Landlord migrations in `database/migrations/` (root). Tenant migrations in `database/migrations/tenant/`; run via `tenants:migrate` with `--path` to tenant folder.

### 4.2 Tenant resolution

- **Mechanism:** Domain-based. `InitializeTenancyByDomain` (Stancl) resolves tenant from request host (subdomain or full domain). `Domain` model (central) links domain → tenant.
- **Config:** `tenancy.central_domains` (CENTRAL_DOMAINS env), `tenancy.tenant_base_domain` (TENANT_BASE_DOMAIN). No path or header-based tenancy for main flows.
- **Where applied:** All tenant API routes (`routes/api/v1/*.php`) and tenant Filament panel use `InitializeTenancyByDomain` + `PreventAccessFromCentralDomains`. Landlord API (`routes/api/landlord/billing.php`) has no tenancy middleware. Web routes (`/`, `/health`) are universal; tenant routes in `routes/tenant.php` are loaded by TenancyServiceProvider (no explicit init in that file; tenant.php runs in tenant context when reached via tenant domain).

### 4.3 Tenant scoping enforcement

- **Model level:** No global scope that auto-adds `tenant_id` on all models. Many tenant-side models have a `tenant_id` column and a `scopeForTenant($query, $tenantId)` used explicitly in Filament resources and repositories (OrderModel, ProductModel, CartModel, PaymentModel, StockItemModel, CategoryModel, CustomerSummaryModel, Invoice, FinancialOrder, Customer, InventoryLocation).
- **Repository level:** Eloquent*Repository in Modules consistently use `Model::forTenant($tenantId)` with `$tenantId = (string) tenant('id')`, so tenant isolation is enforced at repository layer when tenancy is initialized.
- **Controller level:** Tenant API routes run after `InitializeTenancyByDomain`, so default DB connection is the tenant DB; repositories pass `tenant('id')` into `forTenant`. Filament Tenant resources call `getEloquentQuery()->forTenant((string) $tenantId)`.
- **Risk:** Any code that runs in tenant context but uses `Model::query()` without `forTenant()` could still be safe if the default connection is already the tenant DB (so all data is tenant-scoped by connection). The explicit `tenant_id` column and `scopeForTenant` add defense-in-depth and are required when the same model might be queried from central context (e.g. reporting) or when connection could be wrong.

### 4.4 Queries missing tenant isolation

- **Landlord API:** Uses central connection; no tenant scope needed for Plan/Subscription/Tenant. Correct.
- **EnsureTenantNotSuspended:** Uses `Tenant::find($tenantId)` on central DB; correct.
- **FeatureResolver:** Uses `Subscription::on($connection)->where('tenant_id', $tenantId)` on central DB; correct.
- **ReconcileFinancialDataJob:** Runs in central context, then `tenancy()->initialize($tenant)` per tenant; reconciliation runs per tenant DB. Correct.
- **Potential gap:** `routes/tenant.php` only defines `invoices/{invoice}/pdf`; that route runs in tenant context when hit on tenant domain (tenant.php is included without explicit middleware in mapRoutes; tenancy must be initialized by the route group that mounts tenant routes—e.g. universal routes on tenant domain). If tenant routes are only ever hit after domain resolution elsewhere, no missing isolation identified for that route.

### 4.5 Migrations: landlord vs tenant

- **Landlord:** `database/migrations/` (e.g. create_tenants_table, create_plans_table, create_subscriptions_table, create_domains_table, plan_features, landlord_audit_logs, users, etc.).
- **Tenant:** `database/migrations/tenant/` (products, categories, orders, carts, payments, inventory, financial_*, invoices, customers, permissions, audit_logs, etc.). Clearly separated.

### 4.6 Queues and tenant awareness

- **Stancl:** `QueueTenancyBootstrapper` is enabled. Jobs dispatched in tenant context carry tenant context when processed, so queue workers are tenant-aware when the job was serialized in tenant context.
- **ReconcileFinancialDataJob:** Dispatched from central context; in `handle()` it loops over `Tenant::all()` and calls `tenancy()->initialize($tenant)` so each tenant’s reconciliation runs in correct context. Correct.
- **LogAuditEntry:** Accepts `$tenantId`; should be set when dispatched from tenant context so audit logs can be attributed.

### 4.7 Cache and tenant awareness

- **Stancl:** `CacheTenancyBootstrapper` with `tag_base` = `tenant`; cache keys are tagged by tenant so cache is tenant-scoped when in tenant context.
- **FeatureResolver:** Uses `Cache::remember($this->cacheKey($tenantId), ...)` with key `tenant:{id}:features`. In tenant context this is redundant with Stancl’s tag; when called from central (e.g. ReconcileFinancialDataJob) the explicit key ensures isolation.
- **tenant_cache_key():** Helper in `app/Helpers/tenant_cache.php` builds `tenant:{id}:{key}` for explicit tenant-scoped keys when needed outside Stancl’s automatic tagging.

### 4.8 Security risks: tenant leakage

| Risk | Severity | Notes |
|------|----------|--------|
| **API /api/user without tenancy** | Medium | `GET /api/user` uses `auth:sanctum` only. Not under landlord prefix and no InitializeTenancyByDomain. Sanctum guards are `['web', 'customer']`. If called from tenant domain without tenant middleware, tenant context may not be set; response is still the authenticated user (User or Customer). Ambiguity: which user type is expected on this endpoint? Recommend moving under landlord or tenant explicitly and documenting. |
| **Suspended tenant / inactive subscription on API** | Medium | Routes under `api/v1` use `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains` but do **not** apply `subscription.active` or `tenant.not_suspended`. A suspended tenant or tenant without active subscription can still call tenant API until business logic throws. Recommendation: add `tenant.not_suspended` and optionally `subscription.active` to tenant API middleware stack. |
| **Landlord panel on wrong domain** | Low | Mitigated by `EnsureCentralDomain` (404 if host not in central_domains). |
| **Tenant panel on central domain** | Low | Mitigated by `PreventAccessFromCentralDomains` on tenant panel and tenant API. |
| **FeatureResolver cache key** | Low | Key includes tenant id; no cross-tenant cache bleed. |
| **Central User in tenant panel** | Design | Tenant Filament uses guard `web` → User (central). Staff accounts live in central DB; tenant DB holds only tenant data. No leakage; just be aware that tenant dashboard auth is central users. |

---

## 5. LANDLORD VS TENANT DASHBOARD SEPARATION

### 5.1 Route separation

- **Landlord:** Filament panel id `landlord`, path `/admin`. Registered in `LandlordPanelProvider`; middleware stack includes `EnsureCentralDomain` (first). Only reachable when host is in `config('tenancy.central_domains')`.
- **Tenant:** Filament panel id `tenant`, path `/dashboard`. Registered in `TenantPanelProvider`; middleware includes `PreventAccessFromCentralDomains` then `InitializeTenancyByDomain`. Only reachable on non-central (tenant) domains.
- **API:** Landlord API under `Route::prefix('landlord')` in `api.php` (plans, subscriptions, billing). Tenant API under `Route::prefix('v1')` with per-file middleware (InitializeTenancyByDomain + PreventAccessFromCentralDomains). No overlap.

### 5.2 Authentication

- **Landlord panel:** Guard `web`, provider `users` → `App\Models\User`. User uses central DB connection (`getConnectionName()`).
- **Tenant panel:** Guard `web` (same), so same User model and central DB. So: one user table (central), staff log in with same guard; domain + path distinguish panel.
- **Tenant API (customer):** Guard `customer` (Sanctum) with provider `tenant_users` → `App\Models\Customer\Customer`. Customer lives in tenant DB. So: store staff = User (central), store customers = Customer (tenant).

### 5.3 Guards and policies

- **Guards:** `web` (User), `tenant_web` (tenant_users/Customer), `customer` (Sanctum, tenant_users). Landlord and tenant panels both use `web`; tenant API uses `auth:sanctum` and `auth:customer` for customer routes.
- **Policies:** Landlord: PlanPolicy, TenantPolicy, SubscriptionPolicy (in AppServiceProvider). Tenant: ProductPolicy, OrderPolicy, CustomerPolicy, CustomerIdentityPolicy, InvoicePolicy, CurrencyPolicy, InventoryPolicy, InventoryLocationPolicy (for ProductModel, OrderModel, Customer, etc.). Scoped to the model; Filament uses them for authorization. No cross-panel policy reuse.

### 5.4 Shared UI code

- Filament Tenant and Landlord are separate panels (different Resources, Pages, Widgets). No shared Filament resources between landlord and tenant. Shared: Filament base components, Tailwind, Livewire.

### 5.5 Cross-access risk

- **Central domain only:** Landlord panel is 404 on tenant domains. Tenant panel and tenant API are blocked on central domains. So no cross-access by URL.
- **RBAC:** Landlord panel uses `EnsureSuperAdmin` (SuperAdmin, SupportAgent, FinanceAdmin or `is_super_admin`). Tenant panel has no role middleware at panel level; tenant-side permissions (Spatie) are per-tenant. Correctly isolated.

### 5.6 Boundary summary

- **Landlord:** Central domain + `/admin` + User (central) + EnsureCentralDomain + EnsureSuperAdmin. Manages tenants, plans, subscriptions, billing, landlord audit.
- **Tenant:** Tenant domain + `/dashboard` + User (central) for staff + InitializeTenancyByDomain + PreventAccessFromCentralDomains + CheckTenantStatus. Manages products, orders, customers, inventory, invoices, financial, reports. Tenant API: same domain rule, auth:sanctum or auth:customer.

---

## 6. TENANT CONFIGURATION SYSTEM

### 6.1 Where tenant config is stored

- **Plan/feature limits:** Central DB: `plans`, `plan_features`, `features`; resolved via `FeatureResolver` and cached (see below).
- **Stancl TenantConfig:** Feature `TenantConfig::class` is enabled in `config/tenancy.php`. Tenant model has `data` JSON column (and custom columns). Stancl allows reading/writing tenant-specific config via tenant’s `data` or custom attributes. No project code found that reads/writes `tenant->data` for app config except `StripeService` (e.g. Stripe-related data). So: plan/limits = central DB + cache; arbitrary tenant key-value = tenant `data` (underused in codebase).
- **Currency/config files:** `config/currency.php`, `config/system.php` are app-level; not per-tenant. Tenant currency settings are in tenant DB (`tenant_currency_settings`, `tenant_enabled_currencies`).

### 6.2 How configs are loaded

- **Feature/limits:** FeatureResolver::getFeatureValue(), getLimit() → getCachedFeatures() → Cache::remember(tenant:{id}:features, 600, loadFeaturesForTenant). loadFeaturesForTenant uses central connection (Subscription, Plan, plan_features). So: 10 min TTL, central DB as source.
- **Helpers:** tenant_feature($code), tenant_limit($code) call FeatureResolver; require tenant context (tenant('id')).

### 6.3 Runtime caching

- **FeatureResolver:** 600s TTL; invalidateCurrentTenantCache() / invalidateCacheForTenant() for plan/subscription changes.
- **Stancl cache:** Tag-based tenant cache for general Cache usage in tenant context.

### 6.4 Tenant overrides of system defaults

- Plan features and limits are the main “overrides” (per-tenant). No generic “system defaults + tenant overrides” layer beyond plan/features.

### 6.5 Feature flags

- **Tenant-based:** Yes. Plan features (boolean and limit types) drive tenant_feature() / tenant_limit(). CheckTenantFeature middleware and in-code checks (e.g. multi_location_inventory, products_limit) enforce them.

### 6.6 Validation and isolation

- **Config validation:** No formal schema for tenant `data`. Plan/feature values come from DB; type handling in FeatureResolver (boolean, limit).
- **Isolation:** Feature cache key and Stancl cache tagging keep tenant config isolated.

### 6.7 Weaknesses / inconsistencies

- **TenantConfig (Stancl) underused:** Most app config is plan/features or tenant DB tables (e.g. currency). Little use of tenant `data` for config; documentation or consistency with TenantConfig could be improved.
- **subscription.active not on API:** Tenant API does not enforce active subscription middleware; reliance on ad-hoc checks or errors when FeatureResolver throws.
- **FeatureResolver from central:** When ReconcileFinancialDataJob runs, it initializes tenancy so FeatureResolver runs in tenant context; no issue. Any future central-only job that needs “tenant X’s features” must pass tenant id and use cache key explicitly (already supported).

---

## 7. INCOMPLETE OR PARTIAL IMPLEMENTATIONS

### 7.1 TODO / FIXME

- **Grep result:** Only one match in app: `app/Services/Invoice/InvoiceNumberGenerator.php` (comment “Unique per tenant. Counter resets yearly”) — not a TODO. No TODO/FIXME/XXX/HACK found in app code.

### 7.2 Unused / empty / partial

| Item | Location | Notes |
|------|----------|--------|
| **Controller base** | `app/Http/Controllers/Controller.php` | Empty (only `//`). Standard Laravel stub. |
| **API /api/user** | `routes/api.php` | Route exists; purpose (landlord vs tenant) ambiguous; no tenancy middleware. |
| **Marketing placeholder** | `app/Filament/Tenant/Pages/MarketingPlaceholderPage.php` | Name suggests placeholder; not verified for full implementation. |

### 7.3 Partially implemented / to verify

- **Subscription.active / tenant.not_suspended on API:** Middleware exists and is registered but is not applied to any tenant API route group in the scanned routes. So “partial” in the sense of incomplete enforcement.
- **Idempotency middleware:** Registered; not seen on critical payment/checkout routes in the snippets (could be applied elsewhere).
- **SendOrderConfirmationEmailListener:** Registered; implementation not verified (e.g. mail driver, template).

### 7.4 Models without relationships

- Not fully enumerated; typical relationship definitions exist on main models (Order has items, Invoice has items/payments, etc.). Any model missing relationships would need a targeted review.

### 7.5 APIs without validation

- API requests use Form Requests in many places (e.g. CheckoutRequest, ConfirmCheckoutPaymentRequest, CustomerRegisterRequest). Some Module request classes only implement `authorize(): bool` (return true) and may defer validation to constructor or rules(); not all were opened. Recommendation: ensure every API write uses a Form Request with rules().

### 7.6 Endpoints without authorization

- **Public:** Catalog GET products/show (no auth). Customer register, login, forgot-password, reset-password (no auth). Landlord billing webhook (no auth, verified by Stripe signature in controller). Billing success/cancel/portal return (GET; typically redirects after Stripe).
- **Auth required:** Tenant API write routes use auth:sanctum or auth:customer. Filament uses policies. No obvious endpoint that clearly should require auth but does not.

### 7.7 Migrations without foreign keys

- **Grep:** Several migrations use foreignId/foreign/constrained; some tables (e.g. categories with tenant_id, parent_id) have indexes but not all FKs were audited. `database/migrations/tenant/2026_02_24_180000_create_categories_table.php` has no foreign key to tenants (by design in DB-per-tenant; tenant is implicit by DB). Recommendation: ensure referential integrity where applicable (e.g. order_id → orders) in tenant migrations.

### 7.8 Dead code

- No broad dead-code run; `.bak` file present: `database/migrations/2026_02_20_000145_create_subscriptions_table.php.bak` — should be removed from repo.

---

## 8. CODE QUALITY & RISK ANALYSIS

### 8.1 God classes / overloaded controllers

- **CheckoutOrchestrator:** Many dependencies (Cart, Order, Inventory, Payment, Promotion, TransactionManager, Allocation); coordinates flow. Not a god class but the central orchestrator; acceptable.
- **Controllers:** Most API controllers are thin (delegate to services/repositories). ReportsController uses several report services; still manageable.

### 8.2 Fat models

- **FinancialOrder, Invoice:** Multiple methods (scopes, snapshot hash, totals). Reasonable for domain models.
- **Customer, OrderModel:** Relationships and scopes. Within normal range.

### 8.3 Missing transactions

- **CheckoutOrchestrator:** Uses `TransactionManager::run()` for order creation and inventory reserve/release. Good.
- **Payment confirm/refund:** Should wrap state changes in DB transactions; not fully verified in this audit.

### 8.4 Missing indexes

- **Migrations:** Some indexes present (e.g. orders status, tenant_id, unique (tenant_id, slug)). Tenant reporting indexes migration exists (`2026_06_02_100000_add_tenant_reporting_indexes.php`). Full index review would require checking every query pattern.

### 8.5 N+1 risks

- **Filament:** Several resources use `with(...)` (e.g. OrderResource with 'items', InvoiceResource with ['customer','order']). Good. Some list views may still N+1 if relations are not eager-loaded; spot-check recommended.

### 8.6 Abstraction and coupling

- **Modules:** Application layer depends on interfaces (e.g. CartService, PaymentService); Infrastructure provides implementations. Some Infrastructure references Eloquent directly (acceptable). CheckoutOrchestrator depends on concrete PromotionResolverService, PromotionEvaluationService (not interfaces). Moderate coupling.

### 8.7 Hardcoded tenant logic

- **tenant_feature('multi_location_inventory')**, **tenant_limit('products_limit')** and similar are string-based; centralizing feature/limit keys in an enum or config would reduce typos.

### 8.8 Security

- **Stripe webhook:** Must verify signature (StripeWebhookController); not re-verified here.
- **Sanctum:** Used for API; stateful domains in config.
- **Password reset:** Uses Laravel password reset; tenant_users broker for customers.

### 8.9 Module scores (1–10)

| Module | Structure | Isolation | Scalability | Maintainability |
|--------|-----------|-----------|-------------|------------------|
| Landlord Billing | 8 | 9 | 8 | 8 |
| Catalog | 8 | 8 | 8 | 8 |
| Cart | 8 | 8 | 8 | 8 |
| Checkout | 7 | 7 | 7 | 7 |
| Orders | 8 | 8 | 8 | 8 |
| Payments | 8 | 8 | 8 | 7 |
| Inventory | 7 | 8 | 8 | 7 |
| Financial | 7 | 8 | 7 | 7 |
| Invoicing | 7 | 8 | 8 | 7 |
| Customer identity | 7 | 8 | 8 | 7 |
| Reports | 6 | 7 | 7 | 6 |
| Currency | 7 | 8 | 8 | 7 |
| Promotions | 6 | 7 | 7 | 6 |
| Shared | 8 | 9 | 8 | 8 |

---

## 9. SCALABILITY & FUTURE READINESS

### 9.1 Tenant scale

- **10 tenants:** Yes. Database-per-tenant and Horizon/Redis scale easily.
- **100 tenants:** Yes. Same; ensure central DB (plans, subscriptions, tenants) and Redis are sized. FeatureResolver cache reduces central DB load.
- **10,000 tenants:** Possible but operational burden: 10k DBs (connections, migrations, backups). Consider connection pooling, automation for migrations/backups, and monitoring. Queue workers (Horizon) with tenant context and ReconcileFinancialDataJob looping 10k tenants may need batching or per-tenant job dispatch.

### 9.2 Horizontal scaling

- **Stateless app:** Laravel is stateless; scale web nodes behind load balancer. Session uses database/Redis; Sanctum stateful uses same.
- **Queue:** Horizon with Redis; multiple workers. QueueTenancyBootstrapper preserves tenant context across workers.
- **DB:** Central DB can be replicated (read replica). Tenant DBs: one per tenant; no built-in sharding in this codebase.

### 9.3 Queue design

- **Dedicated queues:** Horizon config has default, audit, financial, billing, low. Good for priority and isolation.
- **Tenant jobs:** Dispatched in tenant context when possible so workers run in correct tenant context. ReconcileFinancialDataJob is central and loops tenants; for very large tenant counts, dispatching one job per tenant (with tenant id) would scale better.

### 9.4 Caching strategy

- **Feature/limits:** Cached per tenant (600s). Cache tags (Stancl) and tenant_cache_key() for explicit keys. Good.
- **Currency/exchange rates:** config/currency.php has rate_cache_ttl and settings_cache_ttl; tenant currency settings can be cached. No full audit of all cache usage.

### 9.5 Domain isolation

- **Data:** Strong (database-per-tenant). **Code path:** Shared codebase; tenant identity from domain and middleware. **Config:** Plan/features and tenant DB tables; isolated.

### 9.6 Recommendations

1. **Tenant API middleware:** Add `tenant.not_suspended` and optionally `subscription.active` to the tenant API route group(s) for consistent enforcement.
2. **Clarify /api/user:** Document or move to landlord/tenant and add appropriate tenancy middleware.
3. **ReconcileFinancialDataJob at scale:** Consider dispatching one job per tenant (e.g. ReconcileFinancialDataForTenantJob($tenantId)) instead of looping in one job.
4. **Feature/limit keys:** Centralize in config or enum to avoid typos and simplify changes.
5. **Migrations:** Review tenant migrations for missing foreign keys and indexes on hot paths.
6. **Remove .bak:** Delete `database/migrations/2026_02_20_000145_create_subscriptions_table.php.bak` from the repo.

---

## 10. FINAL EXECUTIVE SUMMARY

### 10.1 Architecture classification

- **Modular monolith** with **database-per-tenant** multi-tenancy (Stancl Tenancy v3). Laravel 12 + Filament 5; separate Landlord and Tenant panels and API surfaces; domain-based tenant resolution; event-driven integration between Orders, Financial, Invoicing, and Payments.

### 10.2 Strengths

- Clear separation of landlord vs tenant routes and panels (domain + path + middleware).
- Database-per-tenant gives strong data isolation and per-tenant backup/restore.
- Stancl bootstrappers for DB, cache, filesystem, queue (and Redis) make tenant context consistent.
- Modular structure (Modules/*, Landlord/*) with DDD-like boundaries in places.
- Feature/limit system (plan-based) with caching and tenant_feature()/tenant_limit() helpers.
- Explicit scopeForTenant and forTenant() in repositories and Filament reduce risk of cross-tenant queries.
- Event-driven flow for order paid → invoice, ledger, financial sync.
- Horizon queue configuration with separate queues for financial/audit/billing.
- Policies and guards separate landlord and tenant models; RBAC on landlord panel (EnsureSuperAdmin).

### 10.3 Weaknesses

- Tenant API does not enforce `subscription.active` or `tenant.not_suspended` middleware; suspended or lapsed tenants can still hit API until logic throws.
- `/api/user` is ambiguous (landlord vs tenant) and has no tenancy middleware.
- ReconcileFinancialDataJob loops all tenants in one job; not ideal for very large tenant counts.
- Some feature/limit keys are string literals; easy to typo.
- TenantConfig (Stancl) and tenant `data` are underused for app-level tenant config.
- One migration `.bak` file in repo.

### 10.4 Immediate fixes

1. Add `tenant.not_suspended` (and optionally `subscription.active`) to tenant API middleware group(s) in `routes/api/v1/*.php` (or a shared group).
2. Resolve `/api/user`: either move under landlord or tenant and add tenancy + guard documentation, or remove if redundant.
3. Delete `database/migrations/2026_02_20_000145_create_subscriptions_table.php.bak`.
4. Verify Stripe webhook signature validation in StripeWebhookController and that payment/refund flows run inside DB transactions.

### 10.5 Long-term refactor suggestions

1. **Per-tenant reconciliation job:** Replace single ReconcileFinancialDataJob loop with a job per tenant (dispatched from scheduler or a small “dispatcher” job) for better scaling and observability.
2. **Feature/limit registry:** Config or enum for feature and limit codes; use in middleware and CheckoutOrchestrator/CreateProduct.
3. **Idempotency:** Apply idempotency middleware to payment confirm and checkout routes if not already.
4. **Tenant config:** Align use of Stancl TenantConfig / tenant `data` for optional tenant overrides and document.
5. **Indexes and FKs:** Full pass on tenant migrations for foreign keys and indexes on high-traffic tables.

### 10.6 Refactor priority order

1. **P0 – Security/consistency:** Tenant API middleware (suspended + subscription), `/api/user` clarification, webhook/transaction verification.
2. **P1 – Hygiene:** Remove .bak file; add missing FKs/indexes where critical.
3. **P2 – Scale:** Per-tenant reconciliation job; centralize feature keys.
4. **P3 – Maintainability:** TenantConfig usage; broader N+1 and index review.

---

*End of audit. All findings are based on the current codebase as of the audit date.*
