# ACTIVITY LOGGING RULES

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
