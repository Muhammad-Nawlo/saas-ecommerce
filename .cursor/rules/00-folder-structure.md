# Saas Ecommerce Enterprise Architecture Rules

1. The project uses Laravel with database per tenant via stancl/tenancy.
2. All code must follow layered modular structure:
   - Domain
   - Application
   - Infrastructure
   - Http
   - Providers
3. No direct business logic inside Controllers or Eloquent Models.
4. All write operations must go through Application Services or Command Handlers.
5. Modules must not directly reference other module’s infrastructure.
6. Use Value Objects for Money, Currency, UUID, Quantity.
7. Always use UUID for primary keys.
8. Tenant isolation:
   - Tenant migrations under database/migrations/tenant.
   - Central migrations under database/migrations.
9. API versioning: /api/v1/<module>.
10. Use spatie/laravel-permission for role/permission per tenant.
11. Use spatie/laravel-activitylog for audit logging.
12. No package EAV or monolithic patterns from Bagisto or Aimeos.
13. All domain services must be registered via ModuleServiceProvider.