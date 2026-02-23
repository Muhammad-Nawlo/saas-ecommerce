# FILAMENT RULES

Using:
- filament/filament

---

## Rule F-1 — Two Separate Panels

Landlord panel:
app/Landlord

Tenant panel:
Modules/*

Do NOT mix data sources.

---

## Rule F-2 — No Business Logic In Filament

Filament Resources must:
- Dispatch Application Commands
- Not calculate pricing
- Not implement access logic
- Not validate plan restrictions

All business logic must exist in Application layer.
