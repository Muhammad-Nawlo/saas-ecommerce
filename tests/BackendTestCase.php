<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base for backend/domain tests that must not depend on Filament or admin UI.
 *
 * In this project, Filament is excluded when APP_ENV=testing (see bootstrap/providers.php),
 * so all tests run without the admin panel. This class exists to:
 * - Document the intent that backend tests extend it when we add Filament back for UI tests.
 * - Provide a single place for backend-only traits (e.g. Queue::fake(), tenant helpers) later.
 *
 * For now, feature tests can keep extending Tests\TestCase. When Filament is loaded
 * for a subset of tests, UI tests should extend TestCase and backend tests BackendTestCase.
 */
abstract class BackendTestCase extends BaseTestCase
{
}
