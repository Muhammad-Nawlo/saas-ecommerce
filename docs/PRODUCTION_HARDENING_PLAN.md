# Production Hardening Implementation Plan

## PHASE 1 — MODULE INTEGRATION STABILIZATION

### 1.1 Module ServiceProviders (extend boot)

**Locations:** Already exist. Add to `boot()`: event listener registration.

- `app/Modules/Catalog/Providers/CatalogServiceProvider.php` — e.g. `Event::listen(ProductCreated::class, ...)`
- `app/Modules/Inventory/Providers/InventoryServiceProvider.php`
- `app/Modules/Cart/Providers/CartServiceProvider.php`
- `app/Modules/Orders/Providers/OrdersServiceProvider.php`
- `app/Modules/Payments/Providers/PaymentsServiceProvider.php`
- `app/Modules/Checkout/Providers/CheckoutServiceProvider.php`
- `app/Landlord/Billing/Providers/BillingServiceProvider.php`

### 1.2 Centralize bindings

All interface→implementation bindings stay in each module’s provider `register()`.

### 1.3 Tenancy checklist

**File:** `docs/PRODUCTION_CHECKLIST_TENANCY.md`

- Landlord models use central connection.
- Tenant routes use `InitializeTenancyBySubdomain` (or domain) and `PreventAccessFromCentralDomains`.
- Tenant models use default connection when tenancy initialized.

---

## PHASE 2 — FULL END-TO-END INTEGRATION TEST

**File:** `tests/Feature/CheckoutFlowTest.php`

**Helper stubs:** `tests/Integration/Helpers/create_tenant_product.php`, `create_tenant_stock.php`, `create_cart_and_add_item.php`, `do_checkout.php`, `confirm_payment.php`

**Flow:** Create tenant → Initialize tenancy → Create product → Create stock → Create cart + add item → Checkout → Confirm payment → Assert order exists, payment succeeded, cart converted.

---

## PHASE 3 — SUBSCRIPTION ENFORCEMENT

**Middleware:** `app/Http/Middleware/EnsureTenantSubscriptionIsActive.php`  
- Resolve `tenant('id')`, query central `subscriptions` by `tenant_id`, require `status` in `['active','trialing']`, else 403.

**Exception:** `app/Modules/Shared/Domain/Exceptions/TenantSuspendedException.php`

**Registration:** `bootstrap/app.php` alias `subscription.active`. Apply to tenant API group: `->middleware('subscription.active')`.

---

## PHASE 4 — PLAN-BASED FEATURE LIMITS

**Migration:** `database/migrations/2026_02_24_130000_add_limit_value_to_plan_features_table.php` — add `limit_value` (nullable int) to `plan_features`.

**Service:** `app/Landlord/Services/FeatureService.php`  
- `hasFeature(tenantId, featureCode): bool`  
- `getFeatureLimit(tenantId, featureCode): ?int`  
- Cache key `tenant:{id}:features`, TTL 600s.

**Enforcement:** See `docs/ENFORCEMENT_SNIPPETS.md` — before product create check `max_products`; before order create check `max_orders_per_month`.

---

## PHASE 5 — EVENT-DRIVEN ARCHITECTURE

**Provider:** `app/Providers/EventServiceProvider.php` — register in `bootstrap/providers.php`.

**Listeners:**
- `app/Listeners/OrderPaidListener.php` — listens to `PaymentSucceeded`, implements `ShouldQueue`
- `app/Listeners/SendOrderConfirmationEmailListener.php` — listens to `PaymentSucceeded`
- `app/Listeners/SubscriptionCancelledListener.php` — listens to `SubscriptionCancelled`

**Queue:** Set `QUEUE_CONNECTION=redis`. Horizon: add `config/horizon.php` (Laravel default) and run `php artisan horizon`.

---

## PHASE 6 — IDEMPOTENCY LAYER

**Migration:** `database/migrations/2026_02_24_130001_create_idempotency_keys_table.php`  
- Central connection; columns: `id`, `tenant_id`, `key`, `endpoint`, `response_hash`, `status_code`, `created_at`, `updated_at`; unique `(tenant_id, key)`.

**Middleware:** `app/Http/Middleware/IdempotencyMiddleware.php`  
- Read header `Idempotency-Key`; cache key `idempotency:{tenant_id}:{key}:{path}`; on success (2xx) cache response body/status; on repeat request return cached response.

**Registration:** `bootstrap/app.php` alias `idempotency`. Apply to checkout/payment routes: `->middleware('idempotency')`.

---

## PHASE 7 — WEBHOOK SAFETY

**Migration:** `database/migrations/2026_02_24_130002_create_stripe_events_table.php`  
- Central; `id`, `event_id` (unique), `processed_at`, timestamps.

**Model:** `app/Landlord/Models/StripeEvent.php` — central connection, HasUuids.

**Enhancement:** `app/Landlord/Billing/Infrastructure/Http/Controllers/BillingWebhookController.php`  
- Already verifies signature via `\Stripe\Webhook::constructEvent`.  
- `alreadyProcessed()`: check Cache and `StripeEvent::where('event_id', $eventId)->exists()`.  
- `markProcessed()`: Cache + `StripeEvent::create([...])`.

---

## PHASE 8 — SOFT DELETE STRATEGY

**Migrations (tenant):**
- `database/migrations/tenant/2026_02_24_130003_add_soft_deletes_to_products_table.php`
- `database/migrations/tenant/2026_02_24_130004_add_soft_deletes_to_carts_table.php`
- `database/migrations/tenant/2026_02_24_130005_add_soft_deletes_to_orders_table.php`

**Models:** Add `SoftDeletes` trait to `ProductModel`, `CartModel`, `OrderModel`.

---

## PHASE 9 — OBSERVABILITY

**Logging:** See `docs/LOGGING_SNIPPETS.md` — payment failure, inventory inconsistency, structured context.

**Migration (tenant):** `database/migrations/tenant/2026_02_24_130006_create_activity_logs_table.php`  
- `id`, `tenant_id`, `entity_type`, `entity_id`, `action`, `payload` (JSON), `created_at`, `updated_at`.

**Model:** `app/Modules/Shared/Infrastructure/Persistence/ActivityLogModel.php` — tenant table `activity_logs`.

---

## PHASE 10 — TESTING STRATEGY

**Structure:** See `tests/README_TEST_STRUCTURE.md`.

- `tests/Unit/Domain/` — aggregate and value object tests
- `tests/Feature/` — HTTP, tenant context, E2E
- `tests/Integration/` — cross-module, helpers

**Definitions:** Aggregate tests (domain rules); Repository tests (mapping); Multi-tenant tests (isolation); Billing/plan enforcement tests.

---

## PHASE 11 — PERFORMANCE HARDENING

**Checklist:** `docs/PERFORMANCE_CHECKLIST.md` — indexes, pagination, caching, Redis.

**Migration (tenant):** `database/migrations/tenant/2026_02_24_130007_add_production_indexes_tenant.php` — indexes on `products`, `orders`, `carts` as applicable.

---

## PHASE 12 — DEPLOYMENT BLUEPRINT

**File:** `docs/DEPLOYMENT_BLUEPRINT.md` — Nginx, PHP-FPM, Redis, Horizon, MySQL (central + tenants), queue workers, S3.

**Env:** `docs/ENV_PRODUCTION_CHECKLIST.md` — APP_ENV, APP_DEBUG, DB, CACHE_DRIVER, QUEUE_CONNECTION, Redis, Stripe, AWS, LOG.

---

## File summary

| Phase | Path |
|-------|------|
| 1 | `docs/PRODUCTION_CHECKLIST_TENANCY.md` |
| 2 | `tests/Feature/CheckoutFlowTest.php`, `tests/Integration/Helpers/*.php` |
| 3 | `app/Http/Middleware/EnsureTenantSubscriptionIsActive.php`, `app/Modules/Shared/Domain/Exceptions/TenantSuspendedException.php`, `bootstrap/app.php` |
| 4 | `database/migrations/2026_02_24_130000_add_limit_value_to_plan_features_table.php`, `app/Landlord/Services/FeatureService.php`, `docs/ENFORCEMENT_SNIPPETS.md` |
| 5 | `app/Providers/EventServiceProvider.php`, `app/Listeners/OrderPaidListener.php`, `app/Listeners/SendOrderConfirmationEmailListener.php`, `app/Listeners/SubscriptionCancelledListener.php`, `bootstrap/providers.php` |
| 6 | `database/migrations/2026_02_24_130001_create_idempotency_keys_table.php`, `app/Http/Middleware/IdempotencyMiddleware.php`, `bootstrap/app.php` |
| 7 | `database/migrations/2026_02_24_130002_create_stripe_events_table.php`, `app/Landlord/Models/StripeEvent.php`, `app/Landlord/Billing/Infrastructure/Http/Controllers/BillingWebhookController.php` |
| 8 | `database/migrations/tenant/2026_02_24_130003-130005_*soft_deletes*.php`, `ProductModel`, `CartModel`, `OrderModel` (SoftDeletes) |
| 9 | `docs/LOGGING_SNIPPETS.md`, `database/migrations/tenant/2026_02_24_130006_create_activity_logs_table.php`, `app/Modules/Shared/Infrastructure/Persistence/ActivityLogModel.php` |
| 10 | `tests/README_TEST_STRUCTURE.md` |
| 11 | `docs/PERFORMANCE_CHECKLIST.md`, `database/migrations/tenant/2026_02_24_130007_add_production_indexes_tenant.php` |
| 12 | `docs/DEPLOYMENT_BLUEPRINT.md`, `docs/ENV_PRODUCTION_CHECKLIST.md` |
