<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLocations extends ListRecords
{
    protected static string $resource = InventoryLocationResource::class;
}
