<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CustomerIdentityResource\Pages;

use App\Filament\Tenant\Resources\CustomerIdentityResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerIdentities extends ListRecords
{
    protected static string $resource = CustomerIdentityResource::class;
}
