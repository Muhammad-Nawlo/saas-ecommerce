# Filament Tenant Panel

## Purpose

Admin UI for tenant users (store staff): path `/dashboard`. Manages products, categories, orders, customers, invoices, financial orders/transactions, inventory (single and multi-location), currencies, exchange rates, tax rates, users, roles/permissions, audit log, and tenant-specific pages (billing, domain settings, store settings). All data is **tenant-scoped**; panel runs after tenancy is initialized (InitializeTenancyByDomain, CheckTenantStatus, etc.).

## Main Resources

- **ProductResource**, **CategoryResource** — Catalog.
- **OrderResource** — Orders.
- **CustomerResource**, **CustomerIdentityResource** — Customers, addresses, orders.
- **InvoiceResource** — Invoices, items, payments, credit notes.
- **FinancialOrderResource**, **FinancialTransactionResource**, **TaxRateResource** — Financial (read/display; creation from order is via listeners).
- **InventoryResource**, **MultiLocation** (InventoryLocation, InventoryStock, InventoryMovement, InventoryTransfer) — Inventory.
- **CurrencyResource**, **ExchangeRateResource**, **TenantCurrencySettingsResource** — Currency.
- **UserResource**, **RoleResource**, **PermissionResource** — Tenant staff and Spatie permissions.
- **AuditLogResource** — Tenant audit log.

## Main Pages

- **BillingPage** — Link to landlord billing/portal.
- **DomainSettingsPage**, **StoreSettingsPage**, **MarketingPlaceholderPage**.

## Widgets

- RevenueByCurrencyWidget, RevenueChartWidget, Orders, LowStock, Conversion, etc.

## Event Flow

- Filament resources trigger Eloquent events and observers; no direct dispatch of OrderPaid/PaymentSucceeded from panel. Financial/Invoice flows are driven by API checkout and payment confirmation.

## External Dependencies

- **Modules** — Catalog, Orders, Cart, Payments, Inventory, Shared (via models).
- **Services** — Invoice, Currency, Financial, Reporting, Promotion.
- **Landlord** — Billing page may redirect to landlord billing; tenant_feature/tenant_limit can gate UI.

## Tenant Context

- **Requires tenant context.** Panel is registered with PreventAccessFromCentralDomains and InitializeTenancyByDomain; CheckTenantStatus ensures tenant is not suspended. All resources use tenant DB connection (tenant-scoped models).

## Financial Data

- **Read/display and indirect writes.** Resources display FinancialOrder, FinancialTransaction, Invoice; creation/update of financial data is via API + listeners, not primarily through Filament forms. Some resources may trigger services that write financial data (e.g. manual invoice issue).
