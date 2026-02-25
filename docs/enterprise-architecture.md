# Enterprise Architecture — Scalability & High Availability

This document describes the current topology, scaling roadmap, and operational readiness for enterprise-scale deployment. No infrastructure code is required beyond what is already in the repo; this is documentation and structural readiness.

---

## 1. Current Topology

### Horizontal deployment (target)

```
                    Load Balancer
                          |
        +-----------------+------------------+
        |                 |                  |
   App Node 1        App Node 2         App Node N
        |                 |                  |
        +-----------------+------------------+
                          |
        +-----------------+------------------+
        |                 |                  |
     Redis            MySQL/PostgreSQL    Object Storage
   (sessions,          (central +           (S3-compatible)
    cache, queues)      tenant DBs)
```

### Stateless application

- **Sessions**: Use Redis in production (`SESSION_DRIVER=redis`, `SESSION_CONNECTION` pointing to Redis). Do not use `file` or `database` for sessions when scaling to multiple app nodes.
- **Cache**: Use Redis (`CACHE_STORE=redis`). Avoid file or database cache for shared state across nodes.
- **No in-memory singleton state**: No tenant data or request-scoped state is kept in process memory across requests. Tenant context is resolved per request (e.g. from domain/subdomain) and DB/cache are used for persistence.

### Shared infrastructure

| Component | Role |
|-----------|------|
| **Redis** | Sessions, cache, queue backend (Horizon). Single instance or cluster. |
| **MySQL/PostgreSQL** | Central (landlord) DB + one DB per tenant (or future shards). |
| **Object storage (S3-compatible)** | Invoices, exports, media. No local-disk dependency in production. |

---

## 2. Scaling Roadmap

- **Short term**: Vertical scaling (larger DB and app instances); read/write DB split (see below).
- **Medium term**: Read replicas for reporting and heavy reads; Horizon workers scaled per queue.
- **Long term**: Tenant sharding via `TenantDatabaseResolver` (range, hash, or region-based) without changing business logic.

---

## 3. Database Scaling

### Read/write split (readiness)

- `config/database.php` supports `read` and `write` host arrays for `mysql` and `pgsql`.
- Set `DB_READ_HOST` (and optionally `DB_WRITE_HOST`) to point to replica(s) and primary. When only `DB_HOST` is set, both read and write use it (no split).
- **No raw `DB::connection('...')` hardcoding**: All DB access uses the default connection or `config('tenancy.database.central_connection')` so that read/write and central/tenant are config-driven.

### Read replica configuration (example)

- **MySQL**: Create a replica; set `DB_READ_HOST=replica.example.com` and `DB_WRITE_HOST=primary.example.com` (or leave unset to use `DB_HOST` for both).
- **Laravel**: Reads (selects) use the first host in `read`; writes and transactions use `write`. Sticky option keeps same connection within a request after a write.

### Failover strategy

- DB failover is handled at the infrastructure layer (e.g. DNS/ProxySQL/HA for MySQL; RDS Multi-AZ). Application assumes a single primary for writes; replicas for reads only.
- Redis: Use Redis Sentinel or managed Redis with HA. Session and cache loss during failover should be acceptable within SLA (re-login, cache refill).

---

## 4. Queue Separation

| Queue | Use | Priority | Retries | Timeout |
|-------|-----|----------|---------|---------|
| **financial** | Payment confirmation, refund ledger, order lock | Highest | 2 | 90s |
| **default** | General jobs | High | 3 | 60s |
| **billing** | Subscription sync, webhooks | Medium | 3 | 90s |
| **audit** / **low** | Audit log, non-critical | Lower | 3 | 60–120s |

- **Horizon** is configured so that `financial` is processed with higher priority (waits, supervisor order) and limited retries to reduce double-processing risk.
- **Idempotency**: Payment confirmation (`payment_confirmed:{paymentId}`) and refund ledger (`refund_ledger:...`) use cache-based idempotency keys so duplicate events do not create duplicate financial records.

---

## 5. Cache Isolation

- **Tenant-scoped cache**: Use the `tenant_cache_key($key, $tenantId = null)` helper for any cache key that holds tenant-specific data (reports, feature usage, currency settings). This guarantees `tenant:{id}:{key}` and prevents cross-tenant bleed when the same Redis is used for multiple tenants.
- **Stancl tenancy**: When running in tenant context, CacheTenancyBootstrapper may also prefix keys; `tenant_cache_key` is required when calling from landlord context with an explicit tenant ID or when the cache store is shared (e.g. Redis cluster).
- **Verified areas**: Currency config, feature limits, product/report caches, and inventory-related caches use tenant prefixing or run in tenant context with the helper where appropriate.

---

## 6. Sharding Extension Point

- **Interface**: `App\Contracts\TenantDatabaseResolver` with `databaseNameForTenant(string $tenantId, string $driver): string`.
- **Current behavior**: Stancl tenancy uses prefix/suffix (e.g. `tenant{id}`). The resolver is an extension point; no sharding is implemented yet.
- **Future strategies** (documented only):
  - **Range-based**: Tenant ID ranges map to DB names (e.g. tenant_0, tenant_1).
  - **Hash-based**: Hash(tenant_id) mod N → shard.
  - **Region-based**: Tenant metadata (region) → regional DB.

---

## 7. Disaster Recovery Outline

- **RTO/RPO**: Define per environment (e.g. RPO 1h, RTO 4h for production).
- **Database**: Daily full backups; point-in-time recovery where supported (e.g. RDS). Tenant DBs included in backup scope.
- **Redis**: Persistence (RDB/AOF) and backup if session/cache durability is required; otherwise treat as ephemeral.
- **Object storage**: Versioning and cross-region replication as per provider (e.g. S3 CRR).

---

## 8. Backup Strategy

| Asset | Strategy |
|-------|----------|
| **DB (central + tenants)** | Automated daily backups; retain per policy; test restore periodically. |
| **Redis** | Snapshot/backup if needed for cache/session; otherwise rebuild from DB. |
| **Storage (S3)** | Versioning; lifecycle; replication to second region if required. |

---

## 9. Zero-Downtime Deploy Strategy

- **Stateless app nodes**: Deploy new code to a subset of nodes; drain and shift traffic (rolling deploy). No in-memory state to migrate.
- **Migrations**: Run migrations in a backward-compatible way (add columns/indexes first; drop later). Use a dedicated migration job or deploy step before switching app version.
- **Queue**: Horizon/workers restart with new code; ensure job retries and idempotency so in-flight jobs are safe (financial jobs use limited retries and idempotency keys).
- **Config/cache**: Use `config:cache` and `route:cache` in production; invalidate or redeploy when config changes.

---

## 10. Object Storage (S3-Compatible)

- **Production**: Set `FILESYSTEM_DISK=s3` and configure `AWS_*` (or `AWS_ENDPOINT` for S3-compatible APIs). Use for invoices, exports, and media so no app node depends on local disk.
- **Tenancy**: Stancl filesystem tenancy can suffix paths per tenant; ensure S3 bucket/path structure supports tenant isolation and IAM/policy as needed.
- **Scaling**: S3 and compatible stores scale horizontally; no application change required beyond config.

---

## 11. Instrumentation & Observability

- **Structured events**: `App\Support\Instrumentation` emits events (e.g. `order_created`, `payment_confirmed`, `invoice_issued`, `refund_processed`, `subscription_changed`) with tenant_id and entity identifiers. Default: log to `stack` channel.
- **Integration**: Wire these to Prometheus, Datadog, NewRelic, or OpenTelemetry by subscribing to log stream or adding a custom driver that pushes to your metrics backend. No vendor lock-in in code.
