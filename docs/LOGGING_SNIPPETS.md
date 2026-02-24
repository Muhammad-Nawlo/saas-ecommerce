# Structured logging examples

## Payment failure

```php
use Illuminate\Support\Facades\Log;

try {
    $this->paymentService->charge(...);
} catch (\Throwable $e) {
    Log::channel('stack')->error('Payment failure', [
        'tenant_id' => tenant('id'),
        'order_id' => $orderId,
        'provider' => 'stripe',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
```

## Inventory inconsistency

```php
Log::channel('stack')->warning('Inventory inconsistency', [
    'tenant_id' => tenant('id'),
    'product_id' => $productId,
    'expected_available' => $expected,
    'actual_available' => $actual,
]);
```

## Activity log (tenant DB)

After critical actions, write to `activity_logs`:

```php
ActivityLogModel::create([
    'tenant_id' => tenant('id'),
    'entity_type' => 'order',
    'entity_id' => $orderId,
    'action' => 'created',
    'payload' => json_encode(['total' => $total]),
]);
```
