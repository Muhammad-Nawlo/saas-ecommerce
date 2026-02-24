<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantCurrencySettingsResource\Pages;

use App\Filament\Tenant\Resources\TenantCurrencySettingsResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenantCurrencySettings extends CreateRecord
{
    protected static string $resource = TenantCurrencySettingsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = (string) tenant('id');
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['tenant_id'] = (string) tenant('id');
        return static::getModel()::create($data);
    }
}
