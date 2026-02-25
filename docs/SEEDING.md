# Database Seeding

## Overview

The project includes full factories and seeders for landlord and tenant data, including financial flow (orders, payments, financial orders, invoices, ledger, refunds) using the application services.

## Requirements

- **Cache:** When running tenant seeders, Stancl tenancy uses cache tags. Use a cache store that supports tagging (e.g. **Redis**). Set `CACHE_STORE=redis` in `.env` when running `php artisan migrate:fresh --seed` so that tenant context cache works.
- **Database:** Central and tenant databases. Tenant DBs are created and migrated automatically when `LandlordSeeder` runs `tenants:migrate`.

## Running the seed

```bash
# Full reset and seed (landlord + all tenants + financial integrity check)
php artisan migrate:fresh --seed --force

# Or use the reseed command (prompts for confirmation unless --force)
php artisan db:reseed --force
```

## Seeder structure

```
DatabaseSeeder
 ├── LandlordSeeder        (super-admin, plans, features, tenants, subscriptions, landlord roles, tenants:migrate)
 ├── TenantSeeder          (for each tenant: TenantDataSeeder with tenancy initialized)
 └── FinancialIntegritySeeder  (verify reconciliation per tenant; throws if mismatch)
```

- **LandlordSeeder:** 1 super-admin user, 2 plans (Basic, Pro), 4 features, plan–feature attachment, 2 tenants (tenant-one → Basic, tenant-two → Pro), domains, subscriptions, landlord roles/permissions.
- **TenantDataSeeder** (per tenant): Tenant roles/permissions, 1 tenant-admin + 1 manager + 1 accountant (users on central, roles on tenant), 3 customers, 20 products, inventory, 5 promotions, 10 carts, 10 orders with payments (PaymentSucceeded → sync/lock/markPaid → invoice + ledger), draft invoices issued, 2 refunds, then `FinancialReconciliationService::verify()`.

## Factories

All main models have factories under `database/factories/` (and `database/factories/Landlord/` for landlord models). Money is in minor units; no floats for currency amounts.

## Safe reseed command

```bash
php artisan db:reseed --force
```

Optionally run a single seeder:

```bash
php artisan db:reseed --force --seed=LandlordSeeder
```
