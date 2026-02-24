<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CategoryResource\Pages;

use App\Filament\Tenant\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            throw new \RuntimeException('Tenant context required');
        }
        $data['id'] = (string) Str::uuid();
        $data['tenant_id'] = (string) $tenantId;
        return $data;
    }
}
