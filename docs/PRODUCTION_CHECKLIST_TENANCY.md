# Tenancy Separation Checklist

## Landlord (central) DB only

- [ ] `app/Landlord/Models/*` — All use `$connection = config('tenancy.database.central_connection')` or `Plan::on($centralConn)`.
- [ ] `app/Landlord/Billing/Infrastructure/Persistence/*Model.php` — Use central connection.
- [ ] `app/Landlord/Services/FeatureResolver.php` — Queries Subscription/Plan on central only.
- [ ] Landlord routes: no `InitializeTenancyBySubdomain` (or equivalent tenant init).
- [ ] Migrations in `database/migrations/` (not `database/migrations/tenant/`) run on default/central.

## Tenant DB only

- [ ] `app/Modules/Catalog/Infrastructure/Persistence/ProductModel.php` — Uses tenant connection (default when tenancy initialized).
- [ ] `app/Modules/Inventory/Infrastructure/Persistence/*` — Tenant.
- [ ] `app/Modules/Cart/Infrastructure/Persistence/*` — Tenant.
- [ ] `app/Modules/Orders/Infrastructure/Persistence/*` — Tenant.
- [ ] `app/Modules/Payments/Infrastructure/Persistence/*` — Tenant.
- [ ] Tenant routes: `InitializeTenancyBySubdomain::class` (or `InitializeTenancyByDomain`) and `PreventAccessFromCentralDomains::class` in middleware.

## Verification

- [ ] Run a request to landlord API (e.g. `/api/landlord/plans`) — ensure no tenant DB is switched.
- [ ] Run a request to tenant API (e.g. `tenant1.saas.test/api/v1/catalog`) — ensure tenant DB is used and central is not overwritten.
- [ ] In tinker: `tenancy()->initialize($tenant)` then `DB::connection()->getDatabaseName()` matches tenant DB name.
