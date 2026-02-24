<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantCurrencySettingsResource\Pages;

use App\Filament\Tenant\Resources\TenantCurrencySettingsResource;
use Filament\Resources\Pages\EditRecord;

class EditTenantCurrencySettings extends EditRecord
{
    protected static string $resource = TenantCurrencySettingsResource::class;
}
