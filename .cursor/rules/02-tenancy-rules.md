# TENANCY RULES (OPTION A - CENTRAL USERS)

Using:
- stancl/tenancy

---

## Rule T-1 — Central Authentication Only

Authentication must occur ONLY in Landlord DB.

Tenant DB must NOT contain:
- passwords
- remember_tokens
- authentication guards

---

## Rule T-2 — Membership Is Required For Tenant Access

Access order:

1. User authenticated
2. Tenant resolved
3. Membership exists
4. Membership status = active
5. Permission check

Controllers must NEVER check permission before membership validation.

---

## Rule T-3 — Tenant-Scoped Roles

Roles table must include:

tenant_id

Unique index required:
(name, tenant_id)

Global roles shared across tenants are forbidden.

---

## Rule T-4 — Tenant User Is Projection

Tenant DB may contain:

tenant_users:
- user_id (UUID from landlord)
- name
- email
- status

Tenant DB must NOT:
- store password
- act as authentication source
