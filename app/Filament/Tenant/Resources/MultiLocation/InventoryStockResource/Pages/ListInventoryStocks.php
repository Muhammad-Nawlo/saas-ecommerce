<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryStockResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryStockResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryStocks extends ListRecords
{
    protected static string $resource = InventoryStockResource::class;
}
