<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\Tenant\Resources\ProductResource;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Shared\Domain\Exceptions\PlanLimitExceededException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            throw new \RuntimeException('Tenant context required');
        }

        $limit = tenant_limit('products_limit');
        if ($limit !== null) {
            $current = ProductModel::forTenant((string) $tenantId)->count();
            if ($current >= $limit) {
                throw PlanLimitExceededException::forFeature('products_limit', $limit);
            }
        }

        $data['id'] = (string) Str::uuid();
        $data['tenant_id'] = (string) $tenantId;
        $data['currency'] = $data['currency'] ?? 'USD';

        return $data;
    }
}
