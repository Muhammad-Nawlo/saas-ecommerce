<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Shared\Infrastructure\Audit\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes a single audit log entry asynchronously. Does not block the request.
 */
class LogAuditEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 60];

    public function __construct(
        public string $dbConnection,
        public string $table,
        public array $attributes,
        public ?string $tenantId = null,
    ) {}

    public function handle(): void
    {
        $log = new AuditLog($this->attributes);
        $log->setConnection($this->dbConnection);
        $log->setTable($this->table);
        $log->save();
    }
}
