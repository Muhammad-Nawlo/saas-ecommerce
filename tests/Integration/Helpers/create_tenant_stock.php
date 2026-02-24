<?php

return function (string $productId): void {
    $handler = app(\App\Modules\Inventory\Application\Handlers\CreateStockHandler::class);
    $tenantId = (string) tenant('id');
    $handler(new \App\Modules\Inventory\Application\Commands\CreateStockCommand(
        tenantId: $tenantId,
        productId: $productId,
        quantity: 100,
        lowStockThreshold: 5
    ));
};
