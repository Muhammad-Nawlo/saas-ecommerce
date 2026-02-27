# Payments Module

## Purpose

Handles payment creation, confirmation, refund, and cancellation. Abstracts payment provider (Stripe) behind PaymentGateway interface. Creates and updates Payment domain entities; dispatches PaymentSucceeded (and other) events that drive Financial, Invoice, and notification listeners.

## Main Models / Entities

- **Payment** (Domain entity) — Aggregate root; amount (Money), provider, provider payment id, status; can record domain events (e.g. PaymentSucceeded).
- **PaymentModel** — Eloquent persistence (tenant DB).

## Main Services

- **PaymentService** — Application service: createPaymentIntent, confirmPayment, refundPayment, dispatchPaymentSucceededIfAny. Uses PaymentRepository and PaymentGatewayResolver. **Confirm/refund have side effects (Stripe API, DB updates, event dispatch).**
- **PaymentGatewayResolver** — Resolves provider (e.g. stripe) to PaymentGateway implementation.
- **StripePaymentGateway** — Implements PaymentGateway; calls Stripe API (create payment intent, confirm, refund).
- **EloquentPaymentRepository** — Implements PaymentRepository; persistence in tenant DB.

## Event Flow

- **PaymentCreated**, **PaymentAuthorized**, **PaymentSucceeded**, **PaymentFailed**, **PaymentRefunded**, **PaymentCancelled** — Domain events. PaymentSucceeded is published by PaymentService (via EventBus) and listened by: CreateInvoiceOnOrderPaidListener, CreateLedgerTransactionOnOrderPaidListener, SyncFinancialOrderOnPaymentSucceededListener, OrderPaidListener, SendOrderConfirmationEmailListener.

## External Dependencies

- **Stripe** — External API (Stripe SDK) via StripePaymentGateway.
- **Shared** — Money value object, EventBus, exceptions.
- **Orders** — Order ID passed when creating payment; order confirmation triggered when payment succeeds.

## Interaction With Other Modules

- **Checkout** — Uses PaymentService to create payment and confirm payment.
- **Orders** — Order marked paid when PaymentSucceeded is handled.
- **Financial / Invoice / Ledger** — Listeners react to PaymentSucceeded and OrderPaid to create FinancialOrder sync, FinancialTransaction, LedgerTransaction, Invoice.

## Tenant Context

- **Requires tenant context.** Payments are stored in tenant DB; gateway calls are tenant-agnostic but payment records are tenant-scoped.

## Financial Data

- **Writes payment records** (amount_cents, currency, status). Financial transactions and ledger entries are created by listeners (CreateFinancialTransactionListener, CreateLedgerTransactionOnOrderPaidListener), not by this module directly. Stripe is external financial system; we record outcome in tenant DB.
