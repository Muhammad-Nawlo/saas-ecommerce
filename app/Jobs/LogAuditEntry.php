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
 * Dispatched to low-priority queue.
 */
class LogAuditEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $connection,
        public string $table,
        public array $attributes,
        public ?string $tenantId = null,
    ) {}

    public function handle(): void
    {
        $log = new AuditLog($this->attributes);
        $log->setConnection($this->connection);
        $log->setTable($this->table);
        $log->save();
    }
}
