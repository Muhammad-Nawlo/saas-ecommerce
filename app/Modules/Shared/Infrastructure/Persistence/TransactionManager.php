<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Persistence;

interface TransactionManager
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function run(callable $callback): mixed;
}
