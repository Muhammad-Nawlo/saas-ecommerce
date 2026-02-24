<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Production health check. Returns connectivity status for database, cache, queue.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $services = [
            'db' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];
        $ok = !in_array(false, $services, true);
        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'services' => $services,
        ], $ok ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->getDatabaseName();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 1, 5);
            $ok = Cache::get($key) === 1;
            Cache::forget($key);
            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            if (config('queue.default') === 'sync') {
                return true;
            }
            $connection = Queue::connection();
            $connection->size('default');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
