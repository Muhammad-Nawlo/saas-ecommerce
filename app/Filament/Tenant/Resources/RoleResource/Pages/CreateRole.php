<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\RoleResource\Pages;

use App\Filament\Tenant\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = $data['guard_name'] ?? 'web';
        return $data;
    }
}
