<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\TenantResource\Pages;

use App\Filament\Landlord\Resources\TenantResource;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;
}
