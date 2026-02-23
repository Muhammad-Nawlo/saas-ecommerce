# AGGREGATE DISCIPLINE RULES

## Rule AG-1 — One Aggregate Root Per Transaction

Application command handlers may persist only ONE aggregate root directly.

Forbidden:
- Saving multiple aggregate roots in the same handler.
- Calling multiple repositories in the same transaction for unrelated aggregates.

Cross-aggregate coordination must use:
- Domain events
- Event listeners
- Eventually consistent processing

---

## Rule AG-2 — Only Aggregate Root May Mutate Children

Child entities must never be mutated directly outside the aggregate root.

Forbidden:
$orderItem->changePrice();

Allowed:
$order->changeItemPrice($itemId, $price);

This protects invariants.
