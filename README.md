# SaaS E-commerce

Multi-tenant SaaS e-commerce platform built with Laravel 12, Filament 5, and Stancl Tenancy. Landlord (central) manages plans, subscriptions, and billing; each tenant has an isolated database and store (catalog, cart, checkout, orders, payments, inventory, customer, reports).

---

## 1. Project overview

- **Architecture:** Modular monolith with **database-per-tenant** multi-tenancy. Single deployable Laravel app; bounded contexts under `app/Modules/` (Catalog, Cart, Checkout, Orders, Payments, Inventory, Financial, Shared) and `app/Landlord/` (Billing, plans, subscriptions).
- **Landlord vs tenant:** Landlord runs on the **central domain** (e.g. `app.example.com`). Tenant stores run on **tenant domains** (e.g. `store1.example.com`). Landlord API: `/api/landlord/*`. Tenant API: `/api/v1/*`.
- **Event-driven financial flow:** Order paid/refunded events drive invoice creation, ledger transactions, and financial sync. Payment confirmation triggers `PaymentSucceeded` → financial order sync, invoice, ledger, order confirmation email.
- **Stripe:** Landlord billing (plans, checkout, subscriptions, webhooks). Tenant payments (Stripe Payment Intents for store checkout).
- **Feature/plan system:** Plans and features live in the central DB. `FeatureResolver` (and helpers `tenant_feature()`, `tenant_limit()`) resolve tenant capabilities from subscription; cache key `tenant:{id}:features` (TTL 600s).

---

## 2. Architecture diagram (text)

```
                         Laravel Application
                                    │
    ┌────────────────────────────────┼────────────────────────────────┐
    │                                │                                │
    ▼                                ▼                                ▼
 Central Domain                 Tenant Domain                    Tenant Domain
 (Landlord)                     (API v1)                         (Filament)
 /admin                         /api/v1/*                       /dashboard
    │                                │                                │
    │ EnsureCentralDomain             │ InitializeTenancyByDomain      │ InitializeTenancyByDomain
    │ EnsureSuperAdmin               │ PreventAccessFromCentral       │ PreventAccessFromCentral
    │                                │                                │ CheckTenantStatus
    ▼                                ▼                                ▼
 Central DB                      Tenant DB                        Tenant DB
 (tenants, plans,                (orders, carts,                  (same as API)
  subscriptions,                  products, payments,
  users, domains)                  inventory, customers)
    │                                │                                │
    │ FeatureResolver ───────────────┼────────────────────────────────┘
    │ Cache: tenant:{id}:features    │
    └──────────────────────────────┴─────────────────────────────────
```

- **Central domain:** Landlord Filament at `/admin`; Landlord API at `/api/landlord/*` (plans, subscriptions, billing checkout, webhook). Uses central database only.
- **Tenant domain:** Tenant API at `/api/v1/*` (catalog, cart, checkout, orders, payments, inventory, customer, reports); tenant Filament at `/dashboard`. Tenant is resolved by **domain**; each tenant has a dedicated database. Cache and filesystem are tenant-isolated (Stancl bootstrappers).

---

## 3. Modules overview

| Module | Purpose |
|--------|--------|
| **Catalog** | Products and categories; list/create/update price/activate/deactivate. Tenant-scoped. |
| **Cart** | Cart and items; add/update/remove, clear, convert to order, abandon. Tenant API. |
| **Checkout** | Full checkout: validate cart, reserve/allocate stock, create order, apply promotions, create payment (Stripe), confirm payment. |
| **Orders** | Order lifecycle: create, add items, confirm, pay, ship, cancel. Events drive Financial/Invoice. |
| **Payments** | Create payment, confirm, refund, cancel. Stripe gateway; PaymentSucceeded drives sync and invoices. |
| **Inventory** | Stock create, increase/decrease, reserve/release, low-stock threshold. Optional multi-location. |
| **Financial** | Financial orders, transactions, ledger; reconciliation (detect mismatches, no auto-fix). Filament + ReconcileFinancialDataJob. |
| **Invoicing** | Invoices from order snapshot; issue, apply payment, credit notes, void. Triggered by OrderPaid listener. |
| **Customer** | Register, login, profile, addresses, password, export (GDPR), delete account. Tenant-scoped; customer guard. |
| **Landlord Billing** | Plans CRUD, subscriptions subscribe/cancel, Stripe checkout, webhook, success/cancel/portal callbacks. Central domain. |
| **Reporting** | Revenue, tax, products, conversion reports. Tenant API; reads orders/financial. |

---

## 4. Tenancy explanation

- **Tenant resolution:** Domain-based. `InitializeTenancyByDomain` (Stancl) resolves tenant from request host (subdomain or custom domain). Central domains list in `config/tenancy.php` (e.g. `CENTRAL_DOMAINS`); all other domains are tenant domains mapped via `domains` table (central DB).
- **DB switching:** On tenant init, Stancl switches the default DB connection to the tenant database (`tenant{uuid}`). Central connection is used for landlord routes and for cross-tenant reads (e.g. FeatureResolver uses central connection explicitly).
- **Feature limits:** `FeatureResolver` reads from central DB (Subscription → Plan → plan_features). Result is cached per tenant (`tenant:{id}:features`, 600s). Use `tenant_feature('code')` for value and `tenant_limit('code')` for numeric limit (-1 = unlimited → null).
- **Cache isolation:** Stancl `CacheTenancyBootstrapper` uses tag-based tenant isolation. Helper `tenant_cache_key($key, $tenantId)` builds keys like `tenant:{id}:{key}` for explicit isolation when needed.

---

## 5. API documentation

- **Swagger UI:** [http://your-host/api/documentation](http://your-host/api/documentation)  
  Interactive docs; all Landlord and Tenant endpoints with request/response schemas and security.

- **Authenticate in Swagger:**  
  - **Staff (tenant or landlord):** Use **bearerAuth**. Obtain a Sanctum token (e.g. login via Filament or token endpoint), then in Swagger UI set **Authorization** to `Bearer <your-token>`.  
  - **Customer:** Use **customerAuth** (same header: `Authorization: Bearer <customer-token>`). Obtain token via `POST /api/v1/customer/login` or register.

- **Testing:** Call Tenant API from the **tenant domain** (e.g. `https://store1.yourapp.com/api/v1/...`). Call Landlord API from the **central domain** (e.g. `https://app.yourapp.com/api/landlord/...`). In Swagger UI you can set the base URL to match the domain you are testing.

- **Spec source:** `storage/api-docs/api-docs.json` (Swagger 2.0). Regenerate with `php artisan swagger:generate` if using annotation-based generation (optional; current setup ships with a pre-built JSON).

---

## 6. Local setup

1. **Install**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Migrate landlord**
   ```bash
   php artisan migrate --force
   ```
   (Uses default DB connection; creates central tables: tenants, plans, subscriptions, domains, users, etc.)

3. **Create a tenant**
   - Via Filament Landlord: go to `/admin`, create a Tenant and add a domain (e.g. `store1.test`).
   - Or via tinker/Seeder: create `Tenant` and `Domain` records on central DB.

4. **Run tenant migrations**
   ```bash
   php artisan tenants:migrate
   ```
   (Runs migrations in `database/migrations/tenant/` for each tenant.)

5. **Seed (optional)**
   ```bash
   php artisan db:seed
   # and/or
   php artisan tenants:seed
   ```

6. **Queue**
   ```bash
   php artisan queue:work
   ```
   (Or Horizon: `php artisan horizon` when using Redis.)

7. **Access panels**
   - Landlord: open central domain (e.g. `http://app.test/admin`).
   - Tenant: open tenant domain (e.g. `http://store1.test/dashboard`).
   - API: tenant endpoints at `http://store1.test/api/v1/...`; landlord at `http://app.test/api/landlord/...`.

8. **API docs**
   - Open `http://your-app-url/api/documentation` (use central or tenant domain; UI loads spec from `/api/documentation/json/api-docs.json`).

---

## 7. Production notes

- **Redis:** Recommended for cache and queue. Horizon requires Redis. Set `CACHE_DRIVER=redis`, `QUEUE_CONNECTION=redis`.
- **Horizon:** Run `php artisan horizon` for queue processing; configure in `config/horizon.php`. Required for tenant DB creation jobs if queued.
- **Stripe webhook:** Configure Stripe to send events to `https://your-central-domain/api/landlord/billing/webhook`. Use webhook signing secret in `.env`; verify signature in `StripeWebhookController`.
- **Central domain config:** Set `CENTRAL_DOMAINS` in `.env` so Landlord routes and Filament are only accessible from that domain. All other domains are treated as tenant domains (must exist in `domains` table).
- **TENANT_BASE_DOMAIN:** Optional; used for subdomain tenant resolution (e.g. `{tenant}.example.com`). Custom domains are stored in `domains` table.

---

## License

Laravel and this project are open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
