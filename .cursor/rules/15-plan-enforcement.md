# PLAN & SUBSCRIPTION ENFORCEMENT RULES

## Rule PLAN-1 — Plan Limits Enforced In Application Layer

Plan checks must occur in:
- Application command handlers
- Domain services

Forbidden:
- Checking plan limits inside controllers
- Checking plan limits inside Filament forms

---

## Rule PLAN-2 — Hard Failures Only

When a limit is reached, throw explicit exception.

Forbidden:
return false;

Correct:
throw new UserLimitExceeded();

---

## Rule PLAN-3 — Plan Checks Precede Permission Checks

Plan restrictions must be validated before permission checks.
