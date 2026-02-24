# Multi-Currency System

Production-grade multi-currency support for the multi-tenant SaaS ecommerce platform. Financial correctness is mandatory: exchange rates are snapshotted at transaction time and never converted dynamically after order/payment/invoice creation.

## Overview

- **Tenant-scoped**: Each tenant has a base currency and optional selling currencies.
- **Immutable rates**: Order, payment, and invoice store `exchange_rate_snapshot`; no recalculation after creation.
- **Money**: All amounts stored as integer minor units (cents); rates as `DECIMAL(18,8)`.
- **Feature flag**: `tenant_feature('multi_currency')` gates multi-currency UI and service behaviour.

## Database (Tenant DB)

### Tables

- **currencies** – ISO 4217 currencies (code, name, symbol, decimal_places, is_active). Seeded with USD, EUR, GBP, TRY, SAR, AED.
- **exchange_rates** – base_currency_id, target_currency_id, rate, source (manual|api), effective_at. Unique on (base, target, effective_at).
- **tenant_currency_settings** – tenant_id (unique), base_currency_id, allow_multi_currency, rounding_strategy (bankers|half_up|half_down).
- **tenant_enabled_currencies** – pivot for which currencies are enabled for selling when allow_multi_currency is true.

### Order / Payment / Invoice

- **financial_orders**: Added base_currency, display_currency, exchange_rate_snapshot, subtotal_base_cents, subtotal_display_cents, tax_base_cents, tax_display_cents, total_base_cents, total_display_cents.
- **payments**: Added payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base.
- **invoices**: Added base_currency, exchange_rate_snapshot, total_base_cents.

## Domain Layer

### CurrencyService

- `getTenantBaseCurrency(?string $tenantId = null): Currency` – Resolves tenant base currency; creates default setting with USD if missing.
- `listEnabledCurrencies(?string $tenantId = null): Collection` – Currencies enabled for selling (or single base when multi-currency off).
- `enableCurrency(int $currencyId, ?string $tenantId = null): void` – Enables a currency for tenant (requires multi_currency feature and allow_multi_currency).
- `disableCurrency(int $currencyId, ?string $tenantId = null): void`.
- `getSettings(?string $tenantId = null): ?TenantCurrencySetting`.

### ExchangeRateService

- `getCurrentRate(Currency $base, Currency $target): ?ExchangeRate` – Latest rate (cached).
- `getRateAt(Currency $base, Currency $target, \DateTimeInterface $at): ?ExchangeRate` – Historical rate.
- `setManualRate(Currency $base, Currency $target, float $rate, ?\DateTimeInterface $effectiveAt = null): ExchangeRate`.
- `updateRatesFromProvider(?string $tenantId = null): int` – Fetches from configured provider, stores as source=api.

### CurrencyConversionService

- `convert(Money $money, Currency $target, ?\DateTimeInterface $at = null): Money` – Converts using tenant rounding strategy.
- `convertWithSnapshot(Money $money, Currency $target, ?\DateTimeInterface $at = null): array{converted: Money, rate_snapshot: array}` – For storing snapshot on order/payment/invoice.

### OrderCurrencySnapshotService

- `fillSnapshot(FinancialOrder $order): void` – Fills base_currency, display_currency, exchange_rate_snapshot, and base/display amount columns. Idempotent.

## Money Value Object

- `Money::fromCents(int $amount, string $currency)`.
- `convertWithRate(float $rate, string $targetCurrency): Money` – Simple conversion with PHP round; use CurrencyConversionService for tenant rounding.
- Never mix currencies in add/subtract without explicit conversion.

## Rate Providers

- **RateProviderInterface**: `fetchRates(Currency $baseCurrency): array<int, float>` (target_currency_id => rate), `getProviderName(): string`.
- **ManualRateProvider**: Returns empty array; rates set via ExchangeRateService::setManualRate.
- **ApiRateProvider**: Stub; uses `config('currency.api_url')` and `config('currency.api_key')`. No hardcoded keys.

Provider is bound in `AppServiceProvider` from `config('currency.rate_provider')` (manual|api).

## Order Snapshot Protection

When an order is locked/created, call `OrderCurrencySnapshotService::fillSnapshot($order)` so that:

- base_currency, display_currency, exchange_rate_snapshot are set.
- subtotal_base_cents, subtotal_display_cents, tax_base_cents, tax_display_cents, total_base_cents, total_display_cents are set.

After creation, these fields must not be changed.

## Payment Integration

Payment record should store:

- payment_currency, payment_amount (minor units), exchange_rate_snapshot, payment_amount_base.

If payment currency differs from order currency, convert using rate at payment time via `CurrencyConversionService::convertWithSnapshot()` and store the snapshot.

## Invoice Integration

Invoice mirrors order currency snapshot: store base_currency, exchange_rate_snapshot, total_base_cents. Totals are not recalculated; copy from order snapshot.

## Product Pricing (Option A)

- Store product price in base currency only.
- Display price: `display_price = convert(base_price, selected_currency)` using CurrencyConversionService (latest rate).

## Filament Tenant Panel

- **CurrencyResource**: List/edit currencies; Enable/Disable for tenant when multi_currency feature is on. Hidden when feature off.
- **ExchangeRateResource**: Set manual rate, view history; scoped to tenant base currency. Hidden when feature off.
- **TenantCurrencySettingsResource**: Base currency, allow_multi_currency toggle, rounding strategy. One row per tenant; create when missing.

## Feature Limit

- `tenant_feature('multi_currency')`: when false, single currency only; Currency and ExchangeRate resources hidden; enableCurrency throws.

## Dashboard Widgets

- **RevenueByCurrencyWidget**: Table of currency, total_cents, order_count from financial_orders (paid/pending).
- **CurrencyDistributionWidget**: Stats overview of revenue per currency.

## Safety Rules

1. Never convert money without a rate snapshot for storage.
2. Never mix currencies in arithmetic.
3. Store both base amount and display amount where applicable.
4. Use DECIMAL(18,8) for rates, integer for money minor units.
5. Run tenant migrations and `CurrencySeeder` in tenant context.

## Configuration

- `config/currency.php`: rate_provider (manual|api), api_url, api_key, rate_cache_ttl.
- Env: `CURRENCY_RATE_PROVIDER`, `CURRENCY_API_URL`, `CURRENCY_API_KEY`, `CURRENCY_RATE_CACHE_TTL`.

## Tests

- Enable/disable currency.
- Set exchange rate and get current rate.
- Convert money (current and historical rate).
- Order snapshot immutability and idempotency.
- Payment convert with snapshot.
- Historical rate conversion.
- Feature limit enforcement (list single currency; enable throws when not allowed).
- Rounding correctness and Money::convertWithRate.

Run: `php artisan test --group=multi_currency`
