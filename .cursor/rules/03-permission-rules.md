# PERMISSION & ROLE RULES

Using:
- spatie/laravel-permission

---

## Rule P-1 — No Spatie In Domain

Forbidden in:
Modules/*/Domain/*

use Spatie\Permission\*

Spatie is Infrastructure only.

---

## Rule P-2 — Permissions Are Code-Defined

Permissions must be seeded.

Example:
catalog.create
catalog.update
inventory.adjust
order.cancel

Tenants may create roles.
Tenants may NOT create permissions dynamically.

---

## Rule P-3 — No Direct Permission Checks In Controllers

Forbidden:
auth()->user()->can(...)

Allowed:
Policy-based authorization

Policies must validate:
1. Tenant active
2. Subscription active
3. Membership active
4. Permission allowed
