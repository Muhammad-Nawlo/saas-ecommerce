<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Landlord\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Central helpers for tenant-dependent tests. Use to avoid cross-test contamination
 * and ensure tenant context is explicit.
 */
final class TenantTestHelper
{
    /**
     * Create a tenant record (no migrations).
     */
    public static function createTenant(array $attributes = []): Tenant
    {
        $defaults = ['name' => 'Test Tenant', 'data' => []];
        $merged = array_merge($defaults, $attributes);
        if (empty($merged['slug'])) {
            $merged['slug'] = Str::slug($merged['name']);
        }
        return Tenant::create($merged);
    }

    /**
     * Create tenant, run tenant migrations and optionally seed roles.
     * Call before tenancy()->initialize($tenant).
     */
    public static function createAndMigrateTenant(array $attributes = [], bool $withRoleSeeder = false): Tenant
    {
        $tenant = self::createTenant($attributes);
        $tenant->run(function () use ($withRoleSeeder): void {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations/tenant'),
                '--force' => true,
            ]);
            if ($withRoleSeeder) {
                (new TenantRoleSeeder())->run();
            }
        });
        return $tenant;
    }

    /**
     * Initialize tenant context for the current test.
     */
    public static function initializeTenant(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
    }

    /**
     * Run central/landlord migrations if landlord_audit_logs table does not exist.
     */
    public static function runCentralMigrations(): void
    {
        if (! Schema::hasTable('landlord_audit_logs')) {
            Artisan::call('migrate', ['--path' => database_path('migrations'), '--force' => true]);
        }
    }

    /**
     * Initialize tenant and act as the given user (or a new factory user).
     * Returns the user for further assertions. Use with Pest: $user = actingAsTenantUser($tenant); $this->actingAs($user);
     */
    public static function actingAsTenantUser(Tenant $tenant, ?User $user = null): User
    {
        self::initializeTenant($tenant);
        $user ??= User::factory()->create();
        return $user;
    }
}
