# Shared Module

## Purpose

Provides cross-cutting domain and infrastructure used by multiple modules: value objects (Money), domain exceptions, audit logging, snapshot hashing (tamper detection), transaction management, and optional event bus. No business workflow; only shared contracts and utilities.

## Main Models / Entities

- None. Contains ValueObjects (Money), Exceptions, AuditLog/TenantAuditLog, ActivityLogModel, SnapshotHash, TransactionManager.

## Main Services / Infrastructure

- **Money** (Value Object) — Single canonical money type: amount in **minor units (cents) only**; no float. Enforces same-currency in add/subtract; throws CurrencyMismatchException. Used by Payments, Checkout, Invoice, Financial.
- **SnapshotHash** — SHA-256 hash of snapshot data for FinancialOrder, Invoice; used for tamper detection (verifySnapshotIntegrity); do not auto-correct.
- **TransactionManager** — Runs callables inside DB transaction; used by CheckoutOrchestrator and others.
- **AuditLogger** / **AuditLog** / **TenantAuditLog** — Structured audit logging; tenant-scoped audit log model.
- **AuditAttributeFilter** — Filters sensitive attributes from audit payloads.
- **EventBus** (optional) — Interface for publishing domain events (e.g. PaymentSucceeded).

## Event Flow

- Shared does not define domain events. Exceptions: PaymentAlreadyProcessedException, PaymentConfirmedException, InvoiceLockedException, FinancialOrderLockedException, CurrencyMismatchException, FeatureNotEnabledException, NoActiveSubscriptionException, TenantSuspendedException, etc.

## External Dependencies

- Laravel DB, Cache, Log; no dependency on other app modules (others depend on Shared).

## Interaction With Other Modules

- **Catalog, Cart, Checkout, Orders, Payments, Inventory** — Use Money, exceptions, TransactionManager, AuditLogger.
- **Financial / Invoice** — Use Money, SnapshotHash, FinancialOrderLockedException, InvoiceLockedException.

## Tenant Context

- **AuditLogger / TenantAuditLog** — Assume tenant context for tenant-scoped audit. TransactionManager and Money are tenant-agnostic. SnapshotHash is stateless.

## Financial Data

- **Money** is the canonical type for monetary amounts (cents only; float forbidden). **SnapshotHash** is used to protect financial snapshot integrity; no direct writes to financial tables in this module.
