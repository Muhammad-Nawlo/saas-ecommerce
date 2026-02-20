# SPATIE PERMISSION HARDENING RULES

Using: spatie/laravel-permission

---

## Rule SP-1 — Roles Must Be Tenant-Scoped

Roles table must contain tenant_id.

Unique index required:
(name, tenant_id)

Global shared roles are forbidden.

---

## Rule SP-2 — Permission Cache Must Be Cleared On Updates

Permission cache must be cleared when:
- Role created
- Role updated
- Permission attached
- Permission detached

Failure to clear cache causes authorization drift.

---

## Rule SP-3 — No Direct use of HasRoles in Domain

Spatie traits must only be used in Infrastructure layer models.
