<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryLocation extends CreateRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');
        return $data;
    }
}
