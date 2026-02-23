<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class LaravelTransactionManager implements TransactionManager
{
    public function begin(): void
    {
        DB::beginTransaction();
    }

    public function commit(): void
    {
        DB::commit();
    }

    public function rollback(): void
    {
        DB::rollBack();
    }

    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
