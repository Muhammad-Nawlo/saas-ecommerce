# Production Deployment Checklist

Use this checklist for each production deployment. No new logic—operational steps only.

## Pre-deploy

- [ ] **APP_DEBUG=false** in production `.env`.
- [ ] **APP_URL** set to the correct base URL (tenant storefront URLs may differ; this is the central app URL if applicable).
- [ ] **CENTRAL_DOMAINS** set to landlord/central domains (comma-separated), e.g. `admin.example.com,landlord.example.com`.
- [ ] **Redis** required: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`. See [REDIS_SETUP.md](REDIS_SETUP.md).
- [ ] **Stripe** (if used): `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` set for production.

## Deploy steps

1. **Pull code** and install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Run migrations** (central + tenants if applicable):
   ```bash
   php artisan migrate --force
   # If using tenancy: php artisan tenants:migrate --force
   ```

3. **Cache config** (recommended for production):
   ```bash
   php artisan config:cache
   ```

4. **Cache routes** (recommended; all routes must be non-closure):
   ```bash
   php artisan route:cache
   ```

5. **Cache views** (optional):
   ```bash
   php artisan view:cache
   ```

6. **Start Horizon** (queue workers):
   ```bash
   php artisan horizon
   ```
   In production, run Horizon under **Supervisor** so it restarts on failure and on deploy. Example Supervisor config:
   ```ini
   [program:horizon]
   process_name=%(program_name)s
   command=php /path/to/artisan horizon
   autostart=true
   autorestart=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/path/to/log/horizon.log
   stopwaitsecs=3600
   ```

## Post-deploy

- [ ] **Health check**: `GET /health` returns 200 and `"status":"ok"`, with `database`, `redis`, `queue` reported.
- [ ] **Monitor** Horizon dashboard (if enabled) and failed job logs.
- [ ] **Redis** must be running before Horizon and web requests that use cache/session.

## Rollback

- Clear caches if needed: `php artisan config:clear`, `php artisan route:clear`, `php artisan view:clear`.
- Restart Horizon after code rollback: `php artisan horizon:terminate` (Supervisor will restart it).

## Performance

- **Indexes**: Migrations add indexes on `tenant_id`, `order_id`, `financial_order_id`, `payment_id`, `invoice_id` where needed (see tenant migrations `add_production_indexes_tenant` and `add_operational_order_id`).
- **N+1**: Orders listing (Filament OrderResource) uses `with('items')`; Financial reports use aggregate queries. Ledger/reconciliation uses `with('entries')` where applicable.

## Notes

- **Proper APP_URL per tenant**: Tenant storefronts may have their own domains; APP_URL typically points to the central/landlord app. Tenant detection uses hostname and `CENTRAL_DOMAINS`; ensure tenant domains are not in CENTRAL_DOMAINS.
- **Config cache**: After `config:cache`, env vars are read from the cached config file. Change env only and re-run `config:cache` to apply.
