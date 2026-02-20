# DATABASE DISCIPLINE RULES

## Rule DB-1 — Landlord And Tenant Migrations Are Separate

Landlord migrations must not create tenant tables.
Tenant migrations must not create landlord tables.

---

## Rule DB-2 — No Schema Changes In Service Providers

Schema creation must occur only in migration files.

Never inside:
- Service Providers
- Boot methods
