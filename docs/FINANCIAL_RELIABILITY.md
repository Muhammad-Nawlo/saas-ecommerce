# Financial reliability (Phase B)

## CI financial safety guard

`php artisan test` runs the full test suite. The following groups must pass for financial integrity:

- **financial** – Full pipeline, immutability, reconciliation, listener failure surfaces
- **financial_pipeline** – FullFinancialPipelineTest (checkout → payment → invoice → refund, ledger balanced, double payment fails)
- **financial_immutability** – FinancialOrder, Invoice, Payment mutation throws
- **reconciliation** – FinancialReconciliationService returns no issues when data consistent; detects unbalanced ledger

To run only financial-related tests:

```bash
php artisan test --group=financial
php artisan test --group=financial_pipeline
php artisan test --group=financial_immutability
php artisan test --group=reconciliation
```

If any of these fail, CI must fail. Do not deploy with failing financial tests.

## Production queue

- Use `QUEUE_CONNECTION=redis` in production. Financial listeners (payment, refund, order lock) must not depend on the database queue in production.
- Horizon is recommended for Redis queue workers and failed-job handling.

## Nightly reconciliation

`ReconcileFinancialDataJob` runs daily at 03:00 (see `routes/console.php`). It iterates all tenants and runs `FinancialReconciliationService::reconcile()`. The service only **detects** inconsistencies (ledger unbalanced, invoice total mismatch, payments sum mismatch) and logs them; it does not auto-fix.
