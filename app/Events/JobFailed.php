<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dispatched when a queued job fails. Use for alerting / monitoring.
 */
class JobFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $connectionName,
        public string $queue,
        public array $payload,
        public Throwable $exception,
    ) {}
}
