<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/**
 * Laravel Horizon configuration stub.
 *
 * Install with: composer require laravel/horizon
 * Then: php artisan horizon:install (optional, to merge with this file)
 *
 * Production: Set QUEUE_CONNECTION=redis and run Horizon with: php artisan horizon.
 * Horizon requires the redis queue driver; with database or sync driver, Horizon will not process jobs.
 * Worker tuning: Increase maxProcesses for high throughput; keep financial/audit
 * workers conservative to avoid duplicate processing under retries.
 */
return [
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'),
    'middleware' => ['web'],
    'waits' => [
        'redis:financial' => 30,
        'redis:default' => 60,
        'redis:billing' => 90,
        'redis:audit' => 120,
        'redis:low' => 120,
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
            'tries' => 2,
            'timeout' => 90,
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
        'supervisor-low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'minProcesses' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-financial' => ['maxProcesses' => 2],
            'supervisor-default' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-audit' => ['maxProcesses' => 2],
            'supervisor-low' => ['maxProcesses' => 2],
            'supervisor-billing' => ['maxProcesses' => 2],
        ],
        'local' => [
            'supervisor-default' => ['maxProcesses' => 3],
            'supervisor-financial' => ['maxProcesses' => 1],
            'supervisor-audit' => ['maxProcesses' => 1],
            'supervisor-low' => ['maxProcesses' => 1],
            'supervisor-billing' => ['maxProcesses' => 1],
        ],
    ],
];
