# ACTIVITY LOG DISCIPLINE RULES

Companion summary of `07-activity-logging.md`.
If guidance differs, `07-activity-logging.md` is authoritative.

Using: spatie/laravel-activitylog

---

## Rule LOG-1 — Logging Via Domain Events Only

Never call activity() directly inside:
- Domain
- Application

Logging must occur via event listeners.

---

## Rule LOG-2 — Sensitive Data Must Never Be Logged

Forbidden fields:
- password
- tokens
- API secrets
- payment data
