# DTO DISCIPLINE RULES

Using: spatie/laravel-data

---

## Rule DTO-1 — DTOs Must Be Immutable

DTOs must:
- Use readonly properties
- Have no setters
- Be validated on creation

---

## Rule DTO-2 — Commands Use DTOs Only

Application command handlers must accept DTOs.

They must not accept:
- Request objects
- Arrays
