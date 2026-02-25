<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Safely reseed the application: migrate fresh and run all seeders.
 * Use --force to skip confirmation (e.g. in CI).
 */
final class ReseedCommand extends Command
{
    protected $signature = 'db:reseed
                            {--force : Skip confirmation}
                            {--seed= : Run only the specified seeder class}';

    protected $description = 'Run migrate:fresh --seed with optional confirmation (landlord + tenants + financial integrity)';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will drop all tables and reseed. Continue?')) {
            return self::FAILURE;
        }

        $this->info('Running migrate:fresh...');
        $this->call('migrate:fresh', ['--force' => true]);

        $seeder = $this->option('seed');
        if ($seeder !== null) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        } else {
            $this->info('Seeding database (Landlord, Tenants, FinancialIntegrity)...');
            $this->call('db:seed', ['--force' => true]);
        }

        $this->info('Reseed complete.');
        return self::SUCCESS;
    }
}
