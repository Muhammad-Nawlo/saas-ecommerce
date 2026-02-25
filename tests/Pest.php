<?php

/*
|--------------------------------------------------------------------------
| Test bootstrap: ensure no cached config/services override bootstrap/providers
|--------------------------------------------------------------------------
| When config (or services) is cached, Laravel skips merging bootstrap/providers.php.
| That would register Filament in tests and break backend-only tests. Clear cache
| for the test run so APP_ENV=testing and bootstrap/providers.php are respected.
*/
$testCacheDir = dirname(__DIR__) . '/bootstrap/cache';
foreach (['config.php', 'services.php'] as $cacheFile) {
    $path = $testCacheDir . '/' . $cacheFile;
    if (file_exists($path)) {
        @unlink($path);
    }
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createAndMigrateTenant(array $attributes = [], bool $withRoleSeeder = false): \App\Landlord\Models\Tenant
{
    return \Tests\Support\TenantTestHelper::createAndMigrateTenant($attributes, $withRoleSeeder);
}

function runCentralMigrations(): void
{
    \Tests\Support\TenantTestHelper::runCentralMigrations();
}

function something()
{
    // ..
}
