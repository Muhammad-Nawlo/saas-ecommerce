# LANDLORD VS TENANT SEPARATION

---

## Rule LT-1 — Landlord DB Contains Only:

- users
- tenants
- memberships
- plans
- subscriptions
- domains

---

## Rule LT-2 — Tenant DB Contains Only:

- tenant_users
- roles
- permissions
- domain module data
- activity_log

---

## Rule LT-3 — No Landlord Model In Tenant Modules

Modules/* must NOT import:

App\Landlord\Models\*

All landlord interactions must occur via Identity module contracts.
