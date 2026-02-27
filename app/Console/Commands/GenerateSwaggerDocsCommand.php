<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Darkaonline\L5Swagger\Generator;
use Illuminate\Console\Command;

/**
 * Generate Swagger/OpenAPI JSON from annotations.
 * Run: php artisan swagger:generate
 * Output is written to storage/api-docs/api-docs.json.
 */
class GenerateSwaggerDocsCommand extends Command
{
    protected $signature = 'swagger:generate';

    protected $description = 'Generate API documentation (Swagger JSON) from annotations';

    public function handle(): int
    {
        $this->info('Generating Swagger docs...');
        Generator::generateDocs();
        $path = config('l5-swagger.doc-dir') . '/api-docs.json';
        $this->info('Docs written to: ' . $path);
        return self::SUCCESS;
    }
}
