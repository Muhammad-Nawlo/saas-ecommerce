# Plan enforcement examples

## Before creating product (max_products)

```php
// In CreateProductHandler or equivalent
$tenantId = (string) tenant('id');
$featureService = app(\App\Landlord\Services\FeatureService::class);
$limit = $featureService->getFeatureLimit($tenantId, 'max_products');
if ($limit !== null) {
    $current = $this->productRepository->countForCurrentTenant();
    if ($current >= $limit) {
        throw \App\Modules\Shared\Domain\Exceptions\PlanLimitExceededException::forFeature('max_products', $limit);
    }
}
```

## Before creating order (max_orders_per_month)

```php
$tenantId = (string) tenant('id');
$featureService = app(\App\Landlord\Services\FeatureService::class);
$limit = $featureService->getFeatureLimit($tenantId, 'max_orders_per_month');
if ($limit !== null) {
    $count = $this->orderRepository->countCreatedThisMonthForTenant($tenantId);
    if ($count >= $limit) {
        throw PlanLimitExceededException::forFeature('max_orders_per_month', $limit);
    }
}
```

Add to OrderRepository interface and implementation:
- `countCreatedThisMonthForTenant(string $tenantId): int`

Seed feature `max_orders_per_month` (type limit) and set per plan in `plan_features`.
