<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit log retention (days)
    |--------------------------------------------------------------------------
    | Prune tenant_audit_logs and landlord_audit_logs older than this.
    | Financial records are never pruned.
    */
    'audit_days' => (int) env('RETENTION_AUDIT_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Inventory movements retention (days)
    |--------------------------------------------------------------------------
    */
    'inventory_movement_days' => (int) env('RETENTION_INVENTORY_MOVEMENT_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Stripe events retention (days) — landlord only
    |--------------------------------------------------------------------------
    */
    'stripe_events_days' => (int) env('RETENTION_STRIPE_EVENTS_DAYS', 90),
];
