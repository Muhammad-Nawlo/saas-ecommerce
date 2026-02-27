<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

/**
 * Root OpenAPI (Swagger 2.0) specification for SaaS E-commerce API.
 *
 * Multi-tenancy:
 * - Tenant API (/api/v1/*) must be called from the tenant domain (e.g. store1.example.com).
 *   Tenant is resolved by domain; each tenant has an isolated database.
 * - Landlord API (/api/landlord/*) must be called from the central domain (e.g. app.example.com).
 *   No tenant context; uses central DB for plans, subscriptions, tenants.
 *
 * Auth:
 * - bearerAuth: Laravel Sanctum token (Authorization: Bearer <token>) for staff/dashboard.
 * - customerAuth: Sanctum token for customer guard (tenant-scoped customer login).
 * - Public endpoints (e.g. catalog products list, register, login, billing callbacks) do not require auth.
 *
 * @SWG\Swagger(
 *     swagger="2.0",
 *     basePath="/api",
 *     schemes={"http", "https"},
 *     consumes={"application/json"},
 *     produces={"application/json"},
 *     @SWG\Info(
 *         title="SaaS E-commerce API",
 *         version="1.0.0",
 *         description="Multi-tenant SaaS with landlord (plans, subscriptions, billing) and tenant (catalog, cart, checkout, orders, payments, inventory, customer, reports) APIs. Domain-based tenant resolution; tenant DB isolated per domain."
 *     ),
 *     @SWG\Tag(name="Landlord - Plans", description="Billing plans (central domain)"),
 *     @SWG\Tag(name="Landlord - Subscriptions", description="Tenant subscriptions (central domain)"),
 *     @SWG\Tag(name="Landlord - Billing", description="Checkout, webhook, callbacks (central domain)"),
 *     @SWG\Tag(name="Tenant - Catalog", description="Products (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Cart", description="Cart and items (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Checkout", description="Checkout and confirm payment (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Orders", description="Orders (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Payments", description="Payments (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Inventory", description="Stock operations (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Customer", description="Customer auth and profile (tenant domain)"),
 *     @SWG\Tag(name="Tenant - Reports", description="Revenue, tax, products, conversion (tenant domain)"),
 *     @SWG\SecurityDefinition(
 *         bearerAuth,
 *         type="apiKey",
 *         in="header",
 *         name="Authorization",
 *         description="Laravel Sanctum bearer token (staff). Example: Bearer 1|..."
 *     ),
 *     @SWG\SecurityDefinition(
 *         customerAuth,
 *         type="apiKey",
 *         in="header",
 *         name="Authorization",
 *         description="Laravel Sanctum bearer token (customer guard). Example: Bearer 2|..."
 *     )
 * )
 *
 * @SWG\Definition(definition="CreatePlanRequest", required={"name","stripe_price_id","price_amount","currency","billing_interval"},
 *   @SWG\Property(property="name", type="string", example="Pro"),
 *   @SWG\Property(property="stripe_price_id", type="string"),
 *   @SWG\Property(property="price_amount", type="integer"),
 *   @SWG\Property(property="currency", type="string", example="USD"),
 *   @SWG\Property(property="billing_interval", type="string", enum={"monthly","yearly"})
 * )
 */
class ApiSpec
{
}
