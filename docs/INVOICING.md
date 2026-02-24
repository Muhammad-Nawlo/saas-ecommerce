# Invoicing System

Legally safe, immutable invoices generated from orders. Multi-tenant; money in cents; tax and totals from snapshot only.

## Database (tenant)

- **invoices** — id (UUID), tenant_id, order_id (FK financial_orders), customer_id (FK customers), invoice_number (unique), status (draft|issued|paid|partially_paid|void|refunded), currency, subtotal_cents, tax_total_cents, discount_total_cents, total_cents, due_date, issued_at, paid_at, snapshot (JSON), locked_at, timestamps, soft deletes.
- **invoice_items** — From order snapshot; description, quantity, unit_price_cents, subtotal_cents, tax_cents, total_cents, metadata.
- **invoice_payments** — amount_cents, currency, paid_at, financial_transaction_id (optional).
- **credit_notes** — reason, amount_cents, currency, issued_at, snapshot (JSON).
- **invoice_number_sequence** — tenant_id, year, last_number (for INV-YYYY-XXXX).

## Flow

1. **Create from order** — `InvoiceService::createFromOrder(FinancialOrder $order)`. Order must be locked (have snapshot) and status paid or pending. Copies snapshot to invoice and items; generates `INV-YYYY-XXXX`; status draft.
2. **Issue** — `InvoiceService::issue(Invoice $invoice)`. Sets issued_at, locked_at, status issued. Invoice is immutable after this.
3. **Apply payment** — `InvoiceService::applyPayment(Invoice $invoice, Money $amount, ?string $financialTransactionId)`. Cannot exceed balance; creates InvoicePayment; updates status to paid or partially_paid.
4. **Credit note** — `InvoiceService::createCreditNote(Invoice $invoice, Money $amount, string $reason)`. Cannot exceed total minus existing credits; snapshots invoice; reduces effective balance.
5. **Void** — `InvoiceService::void(Invoice $invoice)`. Allowed for draft, issued, partially_paid.

## Legal safety

- Invoice number cannot change after issuance.
- Totals cannot change after issuance (all from snapshot).
- Snapshot required before issuance.
- Deleted invoice is soft delete only.
- Credit note required for refunds after issuance.

## Order integration

- Config `invoicing.auto_generate_invoice_on_payment`: when true, `OrderPaid` event creates a draft invoice via `CreateInvoiceOnOrderPaidListener`.
- Invoice stores `order_id` (financial_orders).

## PDF

- **InvoicePdfGenerator::generate(Invoice $invoice)** — Renders from snapshot; requires `barryvdh/laravel-dompdf`. Store path via `config('invoicing.pdf_path')` and `config('invoicing.pdf_disk')`.
- Install: `composer require barryvdh/laravel-dompdf`.

## Filament (tenant)

- **InvoiceResource** (Financial group): list/view/edit (edit only when not locked). Actions: Issue, Mark as paid, Download PDF, Create credit note, Void. Relation managers: Items, Payments, Credit notes.
- Policies: only owner or manager (with `manage invoices`) can issue/void; staff has `view invoices` only.

## Audit

- invoice_created, invoice_issued, invoice_paid, credit_note_created, invoice_voided — with old_status, new_status, actor_id, IP, timestamp (via AuditLogger).

## Config

- `config/invoicing.php`: `auto_generate_invoice_on_payment`, `pdf_disk`, `pdf_path`.

## Tests

Run: `php artisan test tests/Feature/Invoice/`

- Invoice created from order snapshot
- Invoice locked after issuance
- Payment cannot exceed balance
- Credit note cannot exceed total
- Invoice number unique per tenant
- Snapshot immutability after issue
- PDF generated (or clear error when dompdf not installed)

**Note:** If the test run fails during Laravel/Filament bootstrap (e.g. type mismatches in Filament resources), resolve those first. The invoice tests run inside the full application and require the tenant panel and migrations to load.
