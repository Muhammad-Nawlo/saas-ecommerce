<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryMovementResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;
}
