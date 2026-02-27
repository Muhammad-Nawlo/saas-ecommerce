<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Absolute path to location where parsed swagger annotations will be stored
    |--------------------------------------------------------------------------
    */
    'doc-dir' => storage_path() . '/api-docs',

    /*
    |--------------------------------------------------------------------------
    | Relative path to access parsed swagger annotations (JSON spec URL path).
    | UI at /api/documentation; JSON at /api/documentation (default page api-docs.json).
    |--------------------------------------------------------------------------
    */
    'doc-route' => 'api/documentation/json',

    /*
    |--------------------------------------------------------------------------
    | Absolute path to directory containing the swagger annotations.
    |--------------------------------------------------------------------------
    */
    'app-dir' => 'app',

    /*
    |--------------------------------------------------------------------------
    | Directories to exclude from swagger generation
    |--------------------------------------------------------------------------
    */
    'excludes' => [],

    /*
    |--------------------------------------------------------------------------
    | Generate docs on every request (disable in production)
    |--------------------------------------------------------------------------
    */
    'generateAlways' => env('SWAGGER_GENERATE_ALWAYS', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | API Auth token (optional pre-fill in Swagger UI)
    |--------------------------------------------------------------------------
    */
    'api-key' => env('API_AUTH_TOKEN', false),

    'api-key-var' => env('API_KEY_VAR', 'api_key'),

    'api-key-inject' => env('API_KEY_INJECT', 'header'),

    'default-api-version' => env('DEFAULT_API_VERSION', '1'),

    'default-swagger-version' => env('SWAGGER_VERSION', '2.0'),

    'default-base-path' => env('SWAGGER_BASE_PATH', '/api'),

    'behind-reverse-proxy' => false,
];
