# PLAN RESTRICTION RULES

Plan and authorization validation order
(must match AUTH-1 in `12-authorization-pipeline.md`):

1. User authenticated (landlord)
2. Tenant resolved
3. Tenant active
4. Subscription active
5. Feature enabled
6. Membership exists
7. Membership active
8. Permission allowed

Never reverse this order.

---

Plan restrictions apply to:
- max_users
- max_warehouses
- feature flags
- API limits
