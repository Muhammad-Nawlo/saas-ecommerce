# Performance hardening checklist

## Indexes (add via migration)

- **Tenant DB**
  - `products`: index `(tenant_id, is_active)`, index `(tenant_id, slug)`
  - `orders`: index `(tenant_id, created_at)`, index `(tenant_id, status)`
  - `carts`: index `(tenant_id, status)`
  - `cart_items`: index `(cart_id)`
  - `payments`: index `(tenant_id, order_id)`
  - `stock_items`: index `(tenant_id, product_id)`
- **Central**
  - `subscriptions`: index `(tenant_id, status)`
  - `plan_features`: composite `(plan_id, feature_id)` unique (already)

## Query optimization

- [ ] List endpoints use pagination (e.g. `limit` 50, cursor or page).
- [ ] Avoid N+1: eager load relations in repositories (e.g. `order->load('items')`).
- [ ] Use `select()` to limit columns where full model not needed.

## Pagination enforcement

- Catalog products list: `?per_page=50` (max 100).
- Orders list: `?per_page=20`.
- Apply in FormRequest or controller: `$request->input('per_page', 20)` capped.

## Caching

- Catalog list per tenant: `Cache::remember("tenant:{$id}:catalog:products:page:{$page}", 300, fn () => ...)`.
- Feature limits: already cached in FeatureResolver/FeatureService (TTL 600).

## Redis

- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis` (optional)
- Horizon for queue workers.
