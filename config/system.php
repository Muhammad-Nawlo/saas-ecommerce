<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | System read-only mode
    |--------------------------------------------------------------------------
    | When true, all write operations (POST, PUT, PATCH, DELETE) are blocked.
    | Used for maintenance windows and incident response. Reads remain allowed.
    */
    'read_only' => (bool) env('SYSTEM_READ_ONLY', false),
];
