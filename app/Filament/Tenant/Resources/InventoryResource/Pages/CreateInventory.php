<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\InventoryResource\Pages;

use App\Filament\Tenant\Resources\InventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateInventory extends CreateRecord
{
    protected static string $resource = InventoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            throw new \RuntimeException('Tenant context required');
        }
        $data['id'] = (string) Str::uuid();
        $data['tenant_id'] = (string) $tenantId;
        $data['reserved_quantity'] = $data['reserved_quantity'] ?? 0;
        return $data;
    }
}
