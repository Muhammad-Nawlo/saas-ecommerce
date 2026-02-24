<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryLocationResource;
use Filament\Resources\Pages\EditRecord;

class EditInventoryLocation extends EditRecord
{
    protected static string $resource = InventoryLocationResource::class;
}
