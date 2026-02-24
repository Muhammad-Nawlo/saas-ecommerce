<?php

use App\Jobs\ReconcileFinancialDataJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Suspend tenants past 7-day payment grace period (run daily).
Schedule::command('billing:suspend-past-grace-period')->daily();

// Prune old audit logs (tenant 180 days, landlord 365 days).
Schedule::command('audit:prune')->daily();

// Production: prune tenant audit logs, inventory movements, Stripe events per config('retention.*').
Schedule::command('retention:prune')->daily()->at('02:00');

// Financial reconciliation: detect ledger/order/invoice/payment inconsistencies per tenant (detection only).
Schedule::job(new ReconcileFinancialDataJob())->daily()->at('03:00');
