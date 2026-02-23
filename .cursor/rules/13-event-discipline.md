# EVENT & MODULE COMMUNICATION RULES

## Rule EV-1 — Cross-Module Communication Must Use Events

Modules must not call services across boundaries.

Forbidden:
Catalog calling InventoryService directly.

Correct:
Catalog emits ProductDeleted event.
Inventory listens and reacts.

---

## Rule EV-2 — Domain Events Must Be Immutable

Domain events must:
- Have readonly properties
- Contain only primitives / value objects
- Not depend on framework classes
