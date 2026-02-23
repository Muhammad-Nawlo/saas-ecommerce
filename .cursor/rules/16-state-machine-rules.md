# STATE MACHINE DISCIPLINE RULES

Using: spatie/laravel-model-states

---

## Rule ST-1 — Critical Aggregates Must Use States

The following entities must use state classes:

- Tenant
- Subscription
- Membership

Boolean flags like:
is_active
is_suspended

are forbidden.

---

## Rule ST-2 — State Transitions Must Be Explicit

State changes must occur via:
->transitionTo()

Never by directly setting string values.
