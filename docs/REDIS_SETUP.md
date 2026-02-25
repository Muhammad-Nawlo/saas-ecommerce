# Redis Setup (Production)

Production requires Redis for **queue**, **cache**, and **session** when scaling horizontally.

## Required env (production)

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Optional: `REDIS_QUEUE_CONNECTION=default`, `REDIS_CACHE_DB=1`, `REDIS_QUEUE_RETRY_AFTER=90`.

## PHP extension

- **phpredis** (recommended): `pecl install redis` or install package `php-redis`.
- Or **predis**: set `REDIS_CLIENT=predis` and add `predis/predis` to composer.

## Laravel Horizon

Queue workers are managed by [Laravel Horizon](https://laravel.com/docs/horizon).

1. Horizon is configured in `config/horizon.php` (supervisors: default, financial, audit, low, billing).
2. Run workers: `php artisan horizon`
3. In production, run Horizon under Supervisor so it restarts automatically (see [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)).

## Queues

| Queue        | Use case                          |
|-------------|------------------------------------|
| default     | General jobs (order confirmation)  |
| financial   | Payment/financial sync (high priority) |
| audit       | Audit log writes                   |
| low         | Non-urgent (e.g. audit queue name from config) |
| billing     | Landlord billing webhooks          |

## Financial listeners and retries

- **SyncFinancialOrderOnPaymentSucceededListener**: Runs synchronously; idempotent (skips if financial order already paid).
- **CreateInvoiceOnOrderPaidListener**: Idempotent (skips if invoice exists).
- **CreateLedgerTransactionOnOrderPaidListener**: Idempotent (skips if ledger transaction exists for order).
- Queued jobs (e.g. **LogAuditEntry**): Use `tries` and `backoff`; safe to retry.

No business logic changes required for production Redis; ensure `QUEUE_CONNECTION=redis` and Horizon is running.
