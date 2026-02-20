# AUTHORIZATION PIPELINE RULES

## Rule AUTH-1 — Authorization Order Is Fixed

The authorization pipeline must follow this order:

1. User authenticated (landlord)
2. Tenant resolved
3. Tenant active
4. Subscription active
5. Feature allowed by plan
6. Membership exists
7. Membership active
8. Permission allowed

This order must never be changed.

---

## Rule AUTH-2 — No Authorization Logic In Controllers

Controllers must not:
- Inspect membership roles
- Compare role strings
- Access permission arrays directly

Authorization must occur in:
- Policies
- Application services

---

## Rule AUTH-3 — Never Trust Client Tenant Input

Tenant must be resolved from:
- Subdomain
- Domain
- Signed token

Never from:
- Request body
- Query parameters
