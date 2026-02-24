<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryTransferResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryTransferResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryTransfers extends ListRecords
{
    protected static string $resource = InventoryTransferResource::class;
}
