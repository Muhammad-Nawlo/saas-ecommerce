# Phase 3 — Production Hardening

## Queue architecture

- **Config:** `config/queue.php` — use `QUEUE_CONNECTION=redis` in production.
- **Queues:** `audit` (LogAuditEntry), `default` (order confirmation, etc.). Financial/invoice listeners run **sync** to preserve tenant context.
- **Failed jobs:** Logged with structured context; `App\Events\JobFailed` dispatched for alerting.
- **Horizon:** When using Redis, install `laravel/horizon` and use `config/horizon.php` for supervisors (default, audit, financial, billing). Run with `php artisan horizon`.

## Rate limiting

- **checkout:** 30/min per user or IP.
- **payment:** 20/min per user or IP.
- **webhook:** 120/min per IP (Stripe).
- **login:** 10/min per IP (in addition to customer-login where used).

Applied on routes: checkout group, payments group, landlord billing webhook.

## Database

- **Indexes (tenant):** `invoices (order_id, status)`, `payments (order_id, status)`. Run tenant migrations.
- **N+1:** Filament resources use `modifyQueryUsing` with `with()` for eager loading (Invoice, Order, Subscription, etc.).

## Caching

- **Exchange rates:** Already cached per `config('currency.rate_cache_ttl')`.
- **Tenant currency settings:** `CurrencyService::getSettings()` cached with `config('currency.settings_cache_ttl', 300)`.
- **Feature resolution:** `FeatureResolver` caches plan features per tenant (TTL 600s). Do not cache mutable financial aggregates.

## Data retention

- **Config:** `config/retention.php` — `audit_days`, `inventory_movement_days`, `stripe_events_days`.
- **Command:** `php artisan retention:prune` (use `--dry-run` to preview). Prunes tenant audit logs, inventory movements, Stripe events. Financial records are never pruned.
- **Scheduler:** `retention:prune` scheduled daily at 02:00.

## Health check

- **Endpoint:** `GET /health` (JSON). Returns `status` (ok|degraded) and `services.db`, `services.cache`, `services.queue`. Returns 503 if any service fails.

## Observability

- Structured logging with context for: financial order locked, financial order marked paid, invoice issued, payment success (financial sync). Keys include `tenant_id`, `order_id`, `financial_order_id`, `invoice_id` where applicable.

## Idempotency

- **CreateInvoiceOnOrderPaidListener:** Skips if an invoice already exists for the order.
- **CreateFinancialTransactionListener:** Skips if a completed credit transaction already exists for the order.
- **SyncFinancialOrderOnPaymentSucceededListener:** Skips if financial order already exists and is paid.
- **ConfirmPaymentHandler:** Throws `PaymentAlreadyProcessedException` if payment already succeeded.

## Tests

- `HealthEndpointTest`: /health returns JSON and service flags.
- `RateLimiterTest`: Limiters defined.
- `IdempotentFinancialJobTest`: Double OrderPaid does not create duplicate invoice.
- `TenantIsolationTest`: Financial orders isolated per tenant DB.
- `StressCheckoutTest`: Multiple orders and confirmations; no duplicate invoices.

## Deployment checklist

1. Set `QUEUE_CONNECTION=redis` and run Horizon (or another worker) for `default` and `audit`.
2. Configure retention env vars if different from defaults.
3. Ensure scheduler runs (`schedule:run` cron) for `retention:prune` and `audit:prune`.
4. Monitor `/health` and failed job logs (and `JobFailed` event if alerting is wired).
