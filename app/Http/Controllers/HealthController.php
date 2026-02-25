<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Production health check for load balancers and monitoring.
 * GET /health returns JSON: status, database, redis, queue.
 * No sensitive data. Returns 503 if any required service is down.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();
        $redis = $this->checkRedis();
        $queue = $this->checkQueue();
        $ok = $database && $redis && $queue;

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'database' => $database ? 'connected' : 'disconnected',
            'redis' => $redis ? 'connected' : 'disconnected',
            'queue' => $queue ? 'ok' : 'error',
        ], $ok ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        $driver = config('cache.default');
        if ($driver !== 'redis' && config('queue.default') !== 'redis') {
            return true;
        }
        try {
            Redis::connection()->ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $key = 'health_check_' . uniqid('', true);
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
            Queue::connection()->size('default');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
