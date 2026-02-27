# Landlord

## Purpose

Handles central (landlord) concerns: tenant lifecycle, plans, subscriptions, billing (Stripe), feature flags, and tenant database creation/deletion. All landlord data lives on **central DB**; tenant code reads features/limits via FeatureResolver and tenant_feature() / tenant_limit() helpers.

## Main Models

- **Tenant** — Stancl tenant; id, name, status (e.g. active/suspended), data; central DB.
- **Plan** — Billing plan; central DB.
- **Subscription** — Tenant subscription to plan; active/cancelled/past_due; central DB.
- **Feature** — Feature definition (code, type: boolean/limit); central DB.
- **Domain** — Tenant domain (subdomain/custom); central DB.

## Main Services

- **FeatureResolver** — Resolves current tenant's plan features from central DB; cached per tenant (`tenant:{id}:features`). Used by tenant_feature(), tenant_limit(); used by Checkout (multi_location_inventory), product creation (products_limit), middleware (CheckTenantFeature, EnsureTenantSubscriptionIsActive).
- **BillingService** (Landlord/Billing) — Checkout session, subscription create/cancel, portal; Stripe.
- **StripeService** — Stripe API wrapper for landlord billing.
- **FeatureUsageService** — Tracks usage against plan limits (central).

## Event Flow

- **TenantCreated** → JobPipeline (CreateDatabase, MigrateDatabase); **AssignDefaultPlan** (listener).
- **TenantDeleted** → JobPipeline (DeleteDatabase).
- **PlanCreated**, **PlanActivated**, **PlanDeactivated** — Billing domain.
- **SubscriptionCreated**, **SubscriptionActivated**, **SubscriptionCancelled**, **SubscriptionPastDue** — Billing domain; SubscriptionCancelled → SubscriptionCancelledListener.

## External Dependencies

- **Stripe** — Billing checkout, subscriptions, webhooks.
- **Stancl Tenancy** — Tenant creation, DB creation/migration/deletion, domain resolution.
- **Central DB** — All landlord models use central connection.

## Interaction With Other Modules

- **Tenant modules** — FeatureResolver (and thus tenant_feature/tenant_limit) is called from tenant context; reads central DB and cache. Middleware (EnsureTenantSubscriptionIsActive, CheckTenantFeature) protect tenant routes.
- **Filament Landlord** — Admin UI for tenants, plans, subscriptions, features, audit log.

## Tenant Context

- **Landlord runs on central context.** When tenant code calls tenant_feature() or tenant_limit(), the current tenant is resolved (e.g. tenant('id')) and FeatureResolver reads subscription/plan from **central DB** and caches by tenant ID. Cache is tenant-isolated (tenant:{id}:features).

## Financial Data

- **Stripe billing only** (subscription fees, not tenant order revenue). Tenant order revenue is in tenant DB (Financial, Invoice). Landlord does not write tenant financial_orders or ledger.
