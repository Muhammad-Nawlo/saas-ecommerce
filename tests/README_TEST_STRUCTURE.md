# Test structure

```
tests/
  Unit/
    Domain/           # Aggregate tests, value object tests
      Catalog/
      Cart/
      Orders/
  Feature/            # HTTP, tenant context
  Integration/        # Cross-module, DB, helpers
    Helpers/
```

## Definitions

- **Aggregate tests:** Assert domain rules (e.g. Cart cannot add item when status = converted).
- **Repository tests:** Assert mapping domain ↔ persistence (e.g. CartRepository save/load round-trip).
- **Multi-tenant tests:** Assert tenant isolation (e.g. tenant A cannot see tenant B data).
- **Billing enforcement tests:** Assert plan limits and subscription checks (e.g. PlanLimitEnforcementTest).

## Running

- Unit: `php artisan test tests/Unit`
- Feature: `php artisan test tests/Feature`
- Integration: `php artisan test tests/Integration`
- E2E checkout: `php artisan test tests/Feature/CheckoutFlowTest.php`
