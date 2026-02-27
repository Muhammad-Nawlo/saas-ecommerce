# Filament Landlord Panel

## Purpose

Admin UI for landlord (platform) admins: path `/admin`. Manages tenants, plans, subscriptions, features, and landlord audit log. Runs on **central domain** only; middleware EnsureCentralDomain, EnsureSuperAdmin. All data is **central DB**; no tenant context.

## Main Resources

- **TenantResource** — Tenants (create, edit, domains).
- **PlanResource** — Plans, plan features (PlanFeaturesRelationManager).
- **SubscriptionResource** — Subscriptions (tenant, plan, status).
- **FeatureResource** — Feature definitions (code, type).
- **AuditLogResource** — Landlord audit log (central).

## Event Flow

- Creating/editing tenants triggers Stancl events (TenantCreated, etc.). Plan/Subscription/Feature changes affect FeatureResolver cache (invalidate on plan/subscription change if needed).

## External Dependencies

- **Landlord** — Models (Tenant, Plan, Subscription, Feature), BillingService, FeatureResolver.
- **Stancl Tenancy** — Tenant creation triggers database creation/migration.

## Tenant Context

- **No tenant context.** Panel must be accessed from central domain; EnsureCentralDomain and EnsureSuperAdmin enforce this. All queries use central connection.

## Financial Data

- **No tenant financial data.** Landlord billing (Stripe subscriptions) is handled by Landlord HTTP controllers and Stripe; Filament Landlord does not write tenant financial_orders, invoices, or ledger.
