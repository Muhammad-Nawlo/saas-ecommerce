# Catalog Module

## Purpose

Manages product catalog and categories for tenant stores. Provides CRUD for products and categories, product activation/deactivation, and price updates. Used by Cart (product lookup), Checkout (order items), Inventory (stock per product), and Filament tenant admin.

## Main Models / Entities

- **Product** (Domain entity) — Aggregate root; identity, name, price, currency, active state.
- **ProductModel** — Eloquent persistence for products (tenant DB).
- **CategoryModel** — Eloquent persistence for categories (tenant DB).

## Main Services

- **EloquentProductRepository** — Implements `ProductRepository`; loads/saves products in tenant DB.
- **CreateProductHandler** / **UpdateProductPriceHandler** — Application handlers for product creation and price updates.

## Event Flow

- **ProductCreated**, **ProductActivated**, **ProductDeactivated**, **ProductPriceChanged** — Domain events (Catalog); listeners may be registered in CatalogServiceProvider (currently commented).
- **ProductPriceChanged** — Can trigger downstream logic (e.g. cache invalidation); no financial writes.

## External Dependencies

- **Shared** — Exceptions, ValueObjects (Money not used in Catalog; currency stored as string).
- **FeatureResolver** / **tenant_limit** — Product creation may check plan limit (e.g. `products_limit`) via Landlord central DB.

## Interaction With Other Modules

- **Cart** — Reads product data for cart items (price, name).
- **Checkout / Orders** — Order items reference product identity; catalog is read-only in checkout.
- **Inventory** — Stock is tied to product (e.g. `product_id` on stock_items).
- **Filament Tenant** — ProductResource, CategoryResource for admin UI.

## Tenant Context

- **Requires tenant context.** All persistence is tenant-scoped (`tenant_id` or tenant DB). Product and category tables live in tenant database.

## Financial Data

- **Does not write financial data.** Stores product prices (minor units) and currency for display and order calculation only.
