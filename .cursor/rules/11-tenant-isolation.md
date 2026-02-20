# TENANT DATA ISOLATION RULES

Using: stancl/tenancy

---

## Rule TI-1 — No Manual Tenant Filtering

Tenant isolation must rely on tenancy initialization (separate database).

Forbidden:
- Global model scopes for tenant_id filtering
- Manual where('tenant_id', ...) scattered in code

---

## Rule TI-2 — Cache Keys Must Be Tenant-Scoped

All cache keys must include tenant_id.

Correct:
tenant:{tenant_id}:catalog:product:{id}

Forbidden:
catalog:product:{id}

---

## Rule TI-3 — Media Storage Must Be Tenant-Scoped

Media paths must include tenant_id.

Correct:
tenant/{tenant_id}/media/...

Forbidden:
media/shared/...
