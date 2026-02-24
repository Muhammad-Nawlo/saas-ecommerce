<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit table names
    |--------------------------------------------------------------------------
    */
    'tenant_table' => 'tenant_audit_logs',
    'landlord_table' => 'landlord_audit_logs',

    /*
    |--------------------------------------------------------------------------
    | Retention (days). Prune command uses these.
    |--------------------------------------------------------------------------
    */
    'retention_days' => [
        'tenant' => (int) env('AUDIT_RETENTION_DAYS_TENANT', 180),
        'landlord' => (int) env('AUDIT_RETENTION_DAYS_LANDLORD', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attributes to never log (sensitive data)
    |--------------------------------------------------------------------------
    */
    'excluded_attributes' => [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'api_token',
        'secret',
        'stripe_secret',
        'stripe_key',
        'card_number',
        'cvv',
        'cvc',
        'credit_card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue for audit jobs (non-blocking)
    |--------------------------------------------------------------------------
    */
    'queue' => env('AUDIT_QUEUE', 'low'),

    /*
    |--------------------------------------------------------------------------
    | Advanced (optional): event streaming, webhook export, real-time notifications
    |--------------------------------------------------------------------------
    | Prepare for: dispatch events to external stream, webhook activity export,
    | real-time admin notifications. Not implemented by default.
    */
    'event_stream' => [
        'enabled' => false,
    ],
    'webhook_export' => [
        'enabled' => false,
    ],
];
