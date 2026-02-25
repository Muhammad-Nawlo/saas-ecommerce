# Filament 5 Upgrade Report

This document summarizes the changes made to upgrade all Filament resources and related code to **Filament 5** (official docs: https://filamentphp.com/docs/5.x/resources/overview).

---

## 1. Resources Updated

### 1.1 Form API: `Form` → `Schema`

In Filament 5, the Resource form API uses `Filament\Schemas\Schema` instead of `Filament\Forms\Form`. The following were updated:

| Location | Change |
|----------|--------|
| **Tenant Resources** | |
| `OrderResource` | `form(Form $form): Form` → `form(Schema $schema): Schema`, `$form->schema([...])` → `$schema->schema([...])` |
| `CategoryResource` | Same |
| `ProductResource` | Same |
| `UserResource` | Same |
| `InventoryResource` | Same |
| `RoleResource` | Same |
| `CustomerIdentityResource` | Same |
| `FinancialOrderResource` | Same |
| `TaxRateResource` | Same |
| `InvoiceResource` | Same |
| `CurrencyResource` | Same |
| `ExchangeRateResource` | Same |
| `TenantCurrencySettingsResource` | Same |
| `InventoryLocationResource` (MultiLocation) | Same |
| `InventoryTransferResource` (MultiLocation) | Same |
| **Landlord Resources** | |
| `TenantResource` | Same |
| `FeatureResource` | Same |
| `PlanResource` | Same |
| `SubscriptionResource` | Same |

**Imports:** Replaced `use Filament\Forms\Form` with `use Filament\Schemas\Schema` in all of the above. `Filament\Forms\Components\*` (e.g. `Section`, `TextInput`, `Select`) remain unchanged and are used inside the schema.

### 1.2 Relation Managers

| File | Change |
|------|--------|
| `CustomerIdentityResource\RelationManagers\AddressesRelationManager` | `form(Form $form): Form` → `form(Schema $schema): Schema`, `$form->schema([...])` → `$schema->schema([...])` |
| `PlanResource\RelationManagers\PlanFeaturesRelationManager` | Same |

---

## 2. View Pages: Infolist API

In Filament 5, `ViewRecord::infolist()` expects `Schema $schema`, not `Infolist $infolist`. The following pages were updated:

| Page | Change |
|------|--------|
| `AuditLogResource\Pages\ViewAuditLog` (Tenant) | `infolist(Infolist $infolist): Infolist` → `infolist(Schema $schema): Schema`, `$infolist->schema([...])` → `$schema->schema([...])` |
| `FinancialOrderResource\Pages\ViewFinancialOrder` | Same; also switched to `Filament\Schemas\Components\Section` for layout sections. |
| `PermissionResource\Pages\ViewPermission` | Same |

Infolist entry components (`TextEntry`, `KeyValueEntry` from `Filament\Infolists\Components\*`) are still used inside the schema.

---

## 3. Custom Pages: `$view` and Base Class

| Change | Files |
|--------|--------|
| **`$view` non-static** | In Filament 5, `Filament\Pages\Page::$view` is non-static. Updated `protected static string $view` → `protected string $view` in: `BillingPage`, `StoreSettingsPage`, `MarketingPlaceholderPage`, `DomainSettingsPage`. |
| **Missing `use Filament\Pages\Page`** | Added `use Filament\Pages\Page` in `DomainSettingsPage`, `StoreSettingsPage`, `MarketingPlaceholderPage` so they extend the correct base class. |

---

## 4. Widgets

| File | Change |
|------|--------|
| `RevenueChartWidget` | In Filament 5, `ChartWidget::$maxHeight` is non-static. Changed `protected static ?string $maxHeight` → `protected ?string $maxHeight`. |

---

## 5. Other Fixes

| Item | Detail |
|------|--------|
| **FinancialOrderResource** | Removed duplicate `getEloquentQuery()`; kept a single method that applies tenant scope and `->with('items')`. |
| **Tenant AuditLogResource** | The Tenant panel had `AuditLogResource\Pages\ListAuditLogs` and `ViewAuditLog` but no `AuditLogResource.php`. Added `App\Filament\Tenant\Resources\AuditLogResource` using `TenantAuditLog` model, owner-only access, and the existing list/view pages. |

---

## 6. Deprecated APIs Removed

- **`form(Form $form): Form`** — Replaced everywhere with `form(Schema $schema): Schema`.
- **`infolist(Infolist $infolist): Infolist`** — Replaced with `infolist(Schema $schema): Schema` on View pages.
- **`protected static string $view`** on custom Pages — Replaced with `protected string $view`.
- **`protected static ?string $maxHeight`** on chart widget — Replaced with non-static `protected ?string $maxHeight`.

---

## 7. New Syntax Introduced

- **Resource forms:** `Schema $schema` and `$schema->schema([...])` (or `$schema->components([...])`); form field components unchanged.
- **ViewRecord infolist:** `Schema $schema` with `TextEntry`/`KeyValueEntry` and, where used, `Filament\Schemas\Components\Section` for layout.
- **Relation manager forms:** Same as resource forms (`form(Schema $schema): Schema`).

---

## 8. What Was Not Changed

- **Table definitions** — `table(Table $table): Table` and `Tables\Columns\*` / `Tables\Filters\*` / `Tables\Actions\*` left as-is; they are compatible with Filament 5.
- **`getPages()`** — Still returns an array of page name => route registration; no change.
- **`getEloquentQuery()`** — Still used for tenant scoping and query modification.
- **Navigation** — `$navigationIcon`, `$navigationGroup`, `$navigationSort`, `getNavigationLabel()` unchanged.
- **Panel providers** — `TenantPanelProvider` and `LandlordPanelProvider` unchanged; discovery and middleware remain valid.
- **Config** — No `config/filament.php` in the project; no config changes made.

---

## 9. Verification

- `php artisan filament:upgrade` completes successfully.
- `php artisan route:list --path=dashboard` shows all tenant dashboard routes (including audit-logs, categories, orders, etc.).
- All Filament Resources and Relation Managers under `app/Filament/Tenant` and `app/Filament/Landlord` are updated to the form/infolist and page/widget conventions above.

---

## 10. Breaking Changes Addressed

1. **Form signature** — Resources and relation managers that defined `form(Form $form): Form` would not be called correctly by Filament 5; all now use `form(Schema $schema): Schema`.
2. **Infolist on View pages** — Overriding `infolist(Infolist $infolist): Infolist` caused a fatal compatibility error with `ViewRecord::infolist(Schema $schema): Schema`; all such overrides now use `Schema`.
3. **Static `$view` on Page** — Redeclaring `$view` as static caused a fatal error; all custom pages now use a non-static `$view`.
4. **Static `$maxHeight` on ChartWidget** — Same redeclaration issue; fixed in `RevenueChartWidget`.
5. **Duplicate `getEloquentQuery()`** — Removed duplicate in `FinancialOrderResource` and kept a single implementation with tenant scope and `with('items')`.

---

*Report generated as part of the Filament 5 compatibility upgrade.*
