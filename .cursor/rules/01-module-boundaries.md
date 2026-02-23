# MODULE BOUNDARY RULES

## Rule MB-1 — No Cross-Module Infrastructure Usage

A module may NOT import:

Modules\OtherModule\Infrastructure\*
Modules\OtherModule\Http\*

Allowed:
Modules\OtherModule\Domain\Contracts\*

Violation Example:
use Modules\Order\Infrastructure\Persistence\Eloquent\OrderModel;

Correct:
use Modules\Order\Domain\Contracts\OrderRepository;

---

## Rule MB-2 — Domain Is Pure

Files inside:
Modules/*/Domain/*

MUST NOT reference:

Illuminate\
Spatie\
Stancl\
Filament\
DB
Auth
Cache
Request
Eloquent

Domain must be framework-agnostic.

---

## Rule MB-3 — Application Cannot Use Eloquent

Files inside:
Modules/*/Application/*

MUST NOT reference:

Infrastructure\Persistence\Eloquent\
Models\*
DB::
Model::

Application layer works only with:
- Domain Entities
- Domain Contracts
- DTOs

Module: Shared
Domain: Value Objects, Contracts, Exceptions
Entities:
  - Money (minor units, currency)
  - UUID
  - Percentage
Contracts:
  - RepositoryInterface
  - DomainServiceInterface
Exceptions:
  - DomainException
  - InvalidValueException



Module: Catalog

Domain:
  Product
  ProductVariant
  Category
Value Objects:
  - SKU
  - Slug
Commands:
  CreateProduct
  UpdateProduct
Queries:
  ListProducts
  GetProduct
Repositories:
  ProductRepositoryInterface
  CategoryRepositoryInterface
Http:
  API:
    GET /v1/catalog/products
    POST /v1/catalog/products

Module: Pricing

Domain:
  PriceList
  ProductPrice
Value Objects:
  - PriceContext
  - Currency
Application:
  - ResolvePriceService
Repositories:
  - PriceRepositoryInterface
Http:
  API:
    GET /v1/pricing/price?product_id={id}


Module: Pricing

Domain:
  PriceList
  ProductPrice
Value Objects:
  - PriceContext
  - Currency
Application:
  - ResolvePriceService
Repositories:
  - PriceRepositoryInterface
Http:
  API:
    GET /v1/pricing/price?product_id={id}

Module: Promotion

Domain:
  Promotion
  PromotionCondition
  PromotionAction
Value Objects:
  - PromotionContext
Application:
  - PromotionEvaluatorService
Http:
  API:
    GET /v1/promotion/apply


Module: Order

Domain:
  Order
  OrderItem
Events:
  - OrderPlaced
  - OrderCancelled
Commands:
  PlaceOrder
Queries:
  GetOrder
Http:
  API:
    POST /v1/orders
    GET /v1/orders/{id}


Module: Inventory

Domain:
  Warehouse
  StockItem
  StockMovement
Commands:
  ReserveStock
  ReleaseStock
Queries:
  GetStock
Http:
  API:
    GET /v1/inventory/stock

Module: Customer

Domain:
  Customer
  Address
Http:
  API:
    GET /v1/customers
    POST /v1/customers


Module: Subscription

Domain:
  Subscription
  SubscriptionInvoice
Http:
  API:
    GET /v1/subscriptions

Module: Identity

Domain:
  User
  Role
  Permission
Http:
  API:
    POST /v1/auth/login
    POST /v1/auth/logout

Module: Audit

Purpose:
  Wrap spatie/laravel-activitylog
Http:
  API:
    GET /v1/audit/logs