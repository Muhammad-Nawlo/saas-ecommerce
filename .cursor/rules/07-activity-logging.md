# ACTIVITY LOGGING RULES

Canonical source for activity logging constraints.
`17-activity-log-discipline.md` is a condensed companion and must not conflict.

Using:
- spatie/laravel-activitylog

---

## Rule A-1 — Log Via Domain Events Only

Forbidden:
activity()->log(...)

Allowed:
Domain Event → Listener → Log Activity

---

## Rule A-2 — Tenant Context Required

All activity logs must include:
tenant_id
actor_id
entity_type
entity_id
