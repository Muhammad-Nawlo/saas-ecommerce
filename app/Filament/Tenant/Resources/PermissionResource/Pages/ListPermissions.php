<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PermissionResource\Pages;

use App\Filament\Tenant\Resources\PermissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;
}
