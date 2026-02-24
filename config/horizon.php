<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/**
 * Laravel Horizon configuration stub.
 *
 * Install with: composer require laravel/horizon
 * Then: php artisan horizon:install (optional, to merge with this file)
 *
 * Production: Use QUEUE_CONNECTION=redis. Run Horizon with: php artisan horizon
 * Worker tuning: Increase maxProcesses for high throughput; keep financial/audit
 * workers conservative to avoid duplicate processing under retries.
 */
return [
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'),
    'middleware' => ['web'],
    'waits' => [
        'redis:default' => 60,
        'redis:audit' => 120,
        'redis:financial' => 90,
        'redis:billing' => 90,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'silenced' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => false,
    'memory_limit' => 64,

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'minProcesses' => 0,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-audit' => [
            'connection' => 'redis',
            'queue' => ['audit'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'minProcesses' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-financial' => [
            'connection' => 'redis',
            'queue' => ['financial'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'minProcesses' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
        ],
        'supervisor-billing' => [
            'connection' => 'redis',
            'queue' => ['billing'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'minProcesses' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 90,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-audit' => ['maxProcesses' => 2],
            'supervisor-financial' => ['maxProcesses' => 2],
            'supervisor-billing' => ['maxProcesses' => 2],
        ],
        'local' => [
            'supervisor-default' => ['maxProcesses' => 3],
            'supervisor-audit' => ['maxProcesses' => 1],
            'supervisor-financial' => ['maxProcesses' => 1],
            'supervisor-billing' => ['maxProcesses' => 1],
        ],
    ],
];
