# CQRS DISCIPLINE RULES

## Rule CQRS-1 — Commands Must Not Return Collections

Commands modify state.
They may return:
- Aggregate ID
- Void
- Simple result object

They must not return:
- Eloquent collections
- Query builder results

---

## Rule CQRS-2 — Queries Must Not Modify State

Query handlers must not:
- Dispatch events
- Persist aggregates
- Modify domain state
