<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantCurrencySettingsResource\Pages;

use App\Filament\Tenant\Resources\TenantCurrencySettingsResource;
use Filament\Resources\Pages\ListRecords;

class ListTenantCurrencySettings extends ListRecords
{
    protected static string $resource = TenantCurrencySettingsResource::class;
}
