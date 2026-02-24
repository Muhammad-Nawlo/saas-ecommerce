# Deployment blueprint

## Stack

- **Web:** Nginx (reverse proxy) + PHP-FPM
- **App:** Laravel (PHP 8.2+)
- **Queue:** Redis + Laravel Horizon
- **Cache:** Redis
- **DB:** MySQL primary (central); tenant DBs on same or separate MySQL instance
- **Storage:** S3 (or local disk for single-node)
- **Cron:** Scheduler for `schedule:run`; Horizon for queues

## Topology

- One central MySQL database for landlord (billing, tenants, plans, subscriptions, stripe_events, idempotency_keys).
- One MySQL database per tenant (or schema per tenant) for catalog, inventory, orders, cart, payments, activity_logs.
- Redis: one instance for cache, queue, sessions (optional).
- Horizon: run `php artisan horizon` (or supervisor) for queue workers.

## Checklist

- [ ] Run central migrations: `php artisan migrate` (no tenant context).
- [ ] Run tenant migrations: `php artisan tenants:migrate`.
- [ ] Horizon: `php artisan horizon` in production.
- [ ] Scheduler: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.
- [ ] Stripe webhook URL points to `https://your-domain.com/api/landlord/billing/webhook`.
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`.
