# Testing

## Multi-database tenancy

Per [Tenancy for Laravel v3 – Testing](https://tenancyforlaravel.com/docs/v3/testing):

- **Do not use `:memory:` SQLite** for the default database when testing tenant code. The package switches the default database connection; with `:memory:` the central and tenant databases can collide.
- **Do not rely on `RefreshDatabase`** against the default connection for tenant tests for the same reason.

This project uses a **file-based SQLite** database for tests:

- `tests/bootstrap.php` creates `database/testing.sqlite` and sets `DB_DATABASE` to its absolute path so the central app uses that file.
- Tenant databases are created as separate files (e.g. `database/tenant_<uuid>`) by stancl/tenancy.
- All tenant migration calls use `--database=tenant` so migrations run on the tenant connection.

## Running tests

```bash
php artisan test
```

Unit tests and central-app tests run as usual. Tenant tests create a tenant in the test (or via `createAndMigrateTenant()`), initialize tenancy, then run assertions.

## Events

Avoid global `Event::fake()` in tests; tenancy uses events for initialization. Fake only specific events if needed:

```php
Event::fake([MyEvent::class]);
```

## Ending tenancy after each test

If a test leaves the app in tenant context (e.g. ends with `tenancy()->initialize($tenant)`), the default DB connection stays `tenant`. The next test’s `RefreshDatabase` would then run against that tenant DB and the central DB can be left locked. Always **end tenancy** after each test when using tenant context:

```php
afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});
```

Add this in any test file that uses `tenancy()->initialize()` or `TenantTestHelper::initializeTenant()` so the next test runs with the central connection.

## Optional: tenant test case

For many tenant tests you can use a base pattern: create a tenant in `setUp()` and call `tenancy()->initialize($tenant)`. The helper `createAndMigrateTenant()` (from `Tests\Support\TenantTestHelper`) creates a tenant, runs tenant migrations with `--database=tenant`, and returns the tenant for `tenancy()->initialize($tenant)`.
